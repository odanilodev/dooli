# Deploy em produção (cPanel/WHM + Docker)

```
Internet ─443─► Apache do cPanel (AutoSSL) ──► 127.0.0.1:9100 ──► container web (nginx)
                                                                    ├─ SPA Vue
                                                                    ├─ /api, /up → php-fpm (app)
                                                                    └─ /storage
                                              + queue + scheduler + MySQL 8.4 + Redis 7 (sem portas públicas)
```

- Servidor: dedicado com cPanel/WHM (mesmo do IA e da Evolution). Usuário cPanel: `doolicom`. Domínio principal: `dooli.com.br`.
- Código em `/opt/dooli`. Porta local do app: `9100` (4010 = Evolution, 9000 = IA).
- Deploy automático: push na branch `main` → GitHub Actions envia um tarball por SSH → `deploy-prod.sh` (build, up, migrate, caches, queue:restart, health check).

## 1. Preparar o servidor (uma vez, como root)

```bash
ss -tlnp | grep ':9100 '     # tem que vir vazio
mkdir -p /opt/dooli && cd /opt/dooli
```

O `.env.production` é criado depois que o primeiro envio do código trouxer o `.env.production.example` (passo 4). Ele nunca vai pro Git.

Gere os segredos:

```bash
echo "APP_KEY=base64:$(openssl rand -base64 32)"
openssl rand -hex 24   # DB_PASSWORD, DB_ROOT_PASSWORD, REDIS_PASSWORD, *_WEBHOOK_SECRET
```

Troque todos os `CHANGE_ME` (o deploy aborta se sobrar algum) e preencha `RESEND_API_KEY`.

## 2. Apache: proxy do domínio

Mesmo padrão do IA: include **só em `ssl/`**, dentro da pasta do domínio, com backup antes e `reload` (nunca `restart`). O HTTP não é tocado, então o AutoSSL continua validando normalmente.

**Bloco 1: variáveis, checagem e backup**

```bash
DOM=dooli.com.br
CPUSER=$(awk -F': ' -v d="$DOM" '$1==d{print $2}' /etc/userdomains)
echo "usuario cPanel: $CPUSER"          # tem que ser doolicom; se vier vazio, PARE
cp -a /etc/apache2/conf/httpd.conf /root/httpd.conf.bak-dooli-$(date +%F)
cp -a /etc/apache2/conf.d/userdata /root/userdata.bak-dooli-$(date +%F)
```

**Bloco 2: criar o proxy e validar**

```bash
mkdir -p /etc/apache2/conf.d/userdata/ssl/2_4/$CPUSER/$DOM
cat > /etc/apache2/conf.d/userdata/ssl/2_4/$CPUSER/$DOM/proxy.conf <<'EOF'
SSLProxyEngine On
ProxyPreserveHost On

ProxyPass /.well-known/acme-challenge !
ProxyPass /.well-known/pki-validation !
ProxyPass /.well-known/cpanel-dcv !

ProxyPass / http://127.0.0.1:9100/ timeout=300
ProxyPassReverse / http://127.0.0.1:9100/

RequestHeader set X-Forwarded-Proto "https"
RequestHeader set X-Forwarded-Port "443"
EOF
/scripts/rebuildhttpdconf && httpd -t
```

Só continue com `Syntax OK`. Se der erro, não recarregue.

**Bloco 3: aplicar e conferir os outros sites**

```bash
systemctl reload httpd
curl -sI https://novapdv.com.br | head -1
curl -sI https://ia.renovaris.com.br | head -1
curl -sI https://cizap.centrodainteligencia.com.br/ | head -1
```

**Rollback**

```bash
rm /etc/apache2/conf.d/userdata/ssl/2_4/$CPUSER/$DOM/proxy.conf && /scripts/rebuildhttpdconf && httpd -t && systemctl reload httpd
```

Domínios de clientes: se forem **alias/estacionados** do `dooli.com.br`, usam o mesmo vhost e este include já vale. Se forem **addon** (vhost próprio), repita o Bloco 2 com `DOM=dominiodocliente.com.br`.

## 3. Chave SSH e secrets do GitHub

No servidor, crie um par de chaves só para o deploy:

```bash
ssh-keygen -t ed25519 -f /root/.ssh/gh_deploy_dooli -N "" -C "github-actions-dooli"
cat /root/.ssh/gh_deploy_dooli.pub >> /root/.ssh/authorized_keys
cat /root/.ssh/gh_deploy_dooli      # conteúdo vai no secret PROD_SSH_KEY
```

No GitHub (`odanilodev/dooli` → Settings → Secrets and variables → Actions), crie:

| Secret | Valor |
| --- | --- |
| `PROD_HOST` | IP do servidor (`129.121.43.53`) |
| `PROD_USER` | `root` |
| `PROD_PORT` | porta SSH (22 se padrão) |
| `PROD_SSH_KEY` | chave privada gerada acima |
| `PROD_APP_DIR` | `/opt/dooli` |

## 4. Primeiro deploy

1. GitHub → Actions → *Deploy Production* → Run workflow. Ele envia o código para `/opt/dooli` e **falha de propósito** (ainda não há `.env.production`).
2. No servidor: `cd /opt/dooli && cp .env.production.example .env.production && chmod 600 .env.production`, edite e preencha os segredos do passo 1 (APP_KEY, senhas, Resend...).
3. Primeira execução manual (libera a criação do volume do banco):

```bash
cd /opt/dooli && ALLOW_NEW_DB_VOLUME=1 bash deploy-prod.sh
```

A partir daí, todo push na `main` faz o deploy sozinho.

Depois do primeiro deploy, crie a empresa e o admin **reais** (não rode `db:seed` em produção: ele cria `admin@cursos.test` com senha conhecida). Se precisar do menu/robôs padrão:

```bash
docker compose -p dooli -f docker-compose.production.yml --env-file .env.production exec app php artisan tinker
```

## 5. Domínio de cliente

1. O cliente aponta o DNS (registro A) do domínio dele (e do `www`) para o IP do servidor.
2. No cPanel (`doolicom`) adicione o domínio como **Domínio estacionado (alias)** ou **Addon**. O include de proxy já vale; o AutoSSL emite o certificado.
3. No painel do dooli, cadastre o mesmo domínio na empresa (`companies.domain`, sem `www`) com status ativo.

## 6. Operação

```bash
cd /opt/dooli
alias dc='docker compose -p dooli -f docker-compose.production.yml --env-file .env.production'
dc ps
dc logs -f --tail=100 app queue
dc exec app php artisan about
```

Backup (agendar no cron do root): dump do MySQL e do volume `dooli_prod_storage`.

```bash
docker exec dooli_db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --all-databases' | gzip > /root/backups/dooli-$(date +%F).sql.gz
```
