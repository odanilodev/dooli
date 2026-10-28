<template>
  <main class="content public-content legal-page">
    <nav class="public-breadcrumb" aria-label="Você está aqui">
      <router-link :to="publicRoute('public-loja')">Loja</router-link>
      <span>/</span>
      <span class="current">Termos e privacidade</span>
    </nav>

    <header class="legal-hero">
      <div class="legal-hero-copy">
        <p class="public-eyebrow">Documentos legais</p>
        <h1>Termos de Uso e Política de Privacidade</h1>
        <p>
          Entenda as regras para usar a loja e a área de membros, como seus dados são tratados
          e quais são os seus direitos.
        </p>
      </div>
      <div class="legal-meta">
        <span>Última atualização</span>
        <strong>28 de outubro de 2026</strong>
      </div>
    </header>

    <div class="legal-layout">
      <aside class="legal-index">
        <p>Nesta página</p>
        <a href="#termos">
          <span>01</span>
          Termos de uso
        </a>
        <a href="#privacidade">
          <span>02</span>
          Política de privacidade
        </a>
        <router-link :to="publicRoute('public-loja')" class="legal-back">
          Voltar para a loja
        </router-link>
      </aside>

      <article class="legal-doc">
        <section id="termos" class="legal-section">
          <p class="legal-kicker">01 · Termos de uso</p>
          <h2>Regras para uso da plataforma</h2>
          <p>
            Estes termos regulam o acesso à loja, à área de membros e aos conteúdos digitais
            disponibilizados pela {{ companyName }} (“Empresa”).
          </p>

          <h3>1. Aceitação dos termos</h3>
          <p>
            Ao criar uma conta, realizar uma compra ou utilizar a plataforma, você declara que
            leu, entendeu e concorda com estes termos e com a política de privacidade. Caso não
            concorde, não utilize os serviços.
          </p>
          <p>
            Para utilizar a plataforma, você deve ter capacidade legal para assumir obrigações
            e fornecer informações verdadeiras, completas e atualizadas.
          </p>

          <h3>2. Cadastro e segurança da conta</h3>
          <p>
            O acesso a determinados recursos exige a criação de uma conta. Você é responsável
            por manter a confidencialidade da senha e por todas as atividades realizadas na sua
            conta.
          </p>
          <p>
            Em caso de uso não autorizado, perda de acesso ou suspeita de comprometimento da
            conta, comunique a Empresa pelos canais oficiais de atendimento.
          </p>

          <h3>3. Acesso aos conteúdos</h3>
          <p>
            O acesso aos cursos, materiais e demais conteúdos é pessoal, limitado e não
            transferível, observadas as condições do produto adquirido, o período de acesso e as
            regras da turma vinculada.
          </p>
          <p>
            A Empresa pode suspender ou limitar o acesso em caso de inadimplência, fraude,
            compartilhamento indevido de credenciais ou violação destes termos.
          </p>

          <h3>4. Uso permitido</h3>
          <p>Você se compromete a não:</p>
          <ul class="legal-list">
            <li>copiar, gravar, redistribuir, vender ou disponibilizar conteúdos a terceiros;</li>
            <li>compartilhar login, senha ou links de acesso pessoal;</li>
            <li>tentar contornar bloqueios, limites de acesso ou mecanismos de segurança;</li>
            <li>usar a plataforma para atividades ilícitas, ofensivas ou que prejudiquem terceiros;</li>
            <li>automatizar acessos, extrair dados em massa ou sobrecarregar a infraestrutura.</li>
          </ul>

          <h3>5. Conteúdo educacional</h3>
          <p>
            Os conteúdos têm finalidade educacional e informativa. Eles não constituem
            recomendação individual de investimento, consultoria financeira, promessa de
            resultado ou garantia de desempenho.
          </p>

          <h3>6. Propriedade intelectual</h3>
          <p>
            A plataforma, as marcas, os textos, as aulas, os materiais, os layouts e demais
            elementos são protegidos pela legislação aplicável. O uso permitido não transfere ao
            usuário qualquer direito de propriedade intelectual.
          </p>

          <h3>7. Pedidos, pagamentos e acesso</h3>
          <p>
            Os pedidos estão sujeitos à disponibilidade, à confirmação do pagamento e às
            condições exibidas no checkout. O acesso é liberado conforme o status do pedido e as
            regras do produto adquirido.
          </p>
          <p>
            Os dados de pagamento são processados pelos provedores de pagamento utilizados pela
            Empresa. A Empresa não solicita sua senha bancária nem o número completo do cartão
            por canais não oficiais.
          </p>

          <h3>8. Cancelamento, reembolso e suporte</h3>
          <p>
            Pedidos de cancelamento, reembolso e suporte devem ser solicitados pelos canais
            oficiais de atendimento. Serão observados a legislação aplicável, os termos do
            produto e as condições do meio de pagamento utilizado.
          </p>

          <h3>9. Certificados</h3>
          <p>
            Certificados podem ser emitidos quando os critérios de conclusão definidos para o
            curso forem atendidos. A emissão não representa certificação profissional, aval de
            instituição de ensino ou garantia de resultado no mercado.
          </p>

          <h3>10. Disponibilidade e alterações</h3>
          <p>
            A Empresa busca manter a plataforma disponível, mas podem ocorrer manutenções,
            atualizações, falhas técnicas ou interrupções temporárias. Conteúdos e
            funcionalidades podem ser atualizados para melhorar a experiência ou atender
            exigências legais e técnicas.
          </p>

          <h3>11. Responsabilidade</h3>
          <p>
            A Empresa não se responsabiliza por decisões tomadas exclusivamente com base nos
            conteúdos educacionais, por indisponibilidades causadas por terceiros ou por uso
            indevido da conta. Nenhuma disposição destes termos afasta direitos garantidos pela
            legislação de proteção ao consumidor.
          </p>

          <h3>12. Alterações destes termos</h3>
          <p>
            Estes termos podem ser atualizados. A versão vigente será sempre disponibilizada
            nesta página, com a data da última atualização. O uso contínuo da plataforma após a
            publicação de mudanças representa concordância com a nova versão.
          </p>

          <h3>13. Contato</h3>
          <p>
            Dúvidas sobre estes termos devem ser encaminhadas pelos canais oficiais de
            atendimento da {{ companyName }}.
          </p>
        </section>

        <section id="privacidade" class="legal-section">
          <p class="legal-kicker">02 · Política de privacidade</p>
          <h2>Como tratamos seus dados pessoais</h2>
          <p>
            Esta política descreve como a {{ companyName }} coleta, utiliza, armazena e protege
            dados pessoais, em conformidade com a Lei Geral de Proteção de Dados Pessoais
            (LGPD).
          </p>

          <h3>1. Dados coletados</h3>
          <p>Podemos tratar as seguintes categorias de dados:</p>
          <ul class="legal-list">
            <li>dados de cadastro, como nome, e-mail, nome de usuário e senha protegida;</li>
            <li>dados de uso, como cursos acessados, progresso, certificados, comentários e avaliações;</li>
            <li>dados de pedidos, como produtos adquiridos, valores, status e histórico de transações;</li>
            <li>dados técnicos, como endereço IP, navegador, dispositivo e registros de segurança;</li>
            <li>dados de comunicação e atendimento, quando você entra em contato com a Empresa.</li>
          </ul>

          <h3>2. Finalidades do tratamento</h3>
          <p>Os dados podem ser utilizados para:</p>
          <ul class="legal-list">
            <li>criar e gerenciar sua conta, autenticar acessos e liberar conteúdos;</li>
            <li>processar pedidos, pagamentos, assinaturas e emissão de certificados;</li>
            <li>registrar progresso, personalizar a experiência e oferecer suporte;</li>
            <li>prevenir fraudes, garantir a segurança e cumprir obrigações legais;</li>
            <li>enviar comunicações operacionais e, quando autorizado, comunicações informativas;</li>
            <li>manter registros de acesso e melhorar a plataforma.</li>
          </ul>
          <p>
            O tratamento é realizado com base nas hipóteses legais aplicáveis, como execução de
            contrato, cumprimento de obrigação legal, legítimo interesse e consentimento quando
            exigido.
          </p>

          <h3>3. Cookies e tecnologias similares</h3>
          <p>
            A plataforma pode utilizar cookies e armazenamento local estritamente necessários
            para manter a sessão, lembrar preferências, como o tema escolhido, e garantir a
            segurança das operações. Outros recursos de medição ou marketing somente serão
            utilizados quando houver base legal e, se necessário, consentimento.
          </p>

          <h3>4. Compartilhamento de dados</h3>
          <p>
            Os dados podem ser compartilhados, na medida necessária, com provedores de
            pagamento, hospedagem, e-mail, vídeo, suporte e segurança, além de autoridades
            públicas quando houver obrigação legal. Os fornecedores devem tratar os dados apenas
            para as finalidades contratadas e adotar medidas de proteção compatíveis.
          </p>

          <h3>5. Segurança e retenção</h3>
          <p>
            A Empresa adota medidas técnicas e administrativas para proteger os dados contra
            acessos não autorizados, perda, alteração ou divulgação indevida. Os dados são
            mantidos pelo tempo necessário ao cumprimento das finalidades, das obrigações legais
            e do exercício regular de direitos.
          </p>

          <h3>6. Direitos do titular</h3>
          <p>Você pode solicitar, conforme a LGPD:</p>
          <ul class="legal-list">
            <li>confirmação da existência de tratamento e acesso aos dados;</li>
            <li>correção de dados incompletos, inexatos ou desatualizados;</li>
            <li>anonimização, bloqueio ou eliminação de dados desnecessários ou excessivos;</li>
            <li>portabilidade, quando aplicável;</li>
            <li>informações sobre compartilhamentos e sobre a possibilidade de não consentir;</li>
            <li>revogação do consentimento e oposição a tratamentos, quando cabível.</li>
          </ul>
          <p>
            Alguns pedidos podem ser limitados por obrigações legais, regulatórias ou pelo
            exercício regular de direitos da Empresa.
          </p>

          <h3>7. Contato e atualizações</h3>
          <p>
            Para exercer seus direitos ou esclarecer dúvidas sobre privacidade, utilize os canais
            oficiais de atendimento da {{ companyName }}. Esta política pode ser atualizada para
            refletir mudanças legais, técnicas ou operacionais.
          </p>

          <div class="legal-note">
            <svg class="icon" width="18" height="18" viewBox="0 0 24 24" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
            <p>
              Este documento apresenta as regras gerais da plataforma. Em caso de dúvidas sobre
              uma situação específica, procure a Empresa ou consulte um profissional jurídico.
            </p>
          </div>
        </section>
      </article>
    </div>
  </main>
</template>

<script setup>
import { computed } from 'vue';
import { useTenant } from '../composables/useTenant';
import { useStorefrontStore } from '../stores/storefront';

const storefront = useStorefrontStore();
const { publicRoute } = useTenant();

const companyName = computed(
  () => storefront.company?.name || 'empresa responsável por esta plataforma',
);
</script>

<style scoped>
.legal-page{gap:30px;}
.legal-hero{
  display:grid;grid-template-columns:minmax(0,1fr) auto;gap:32px;align-items:end;
  padding:clamp(28px,4vw,52px);border:1px solid var(--border);border-radius:26px;
  background:
    radial-gradient(circle at 88% 12%,color-mix(in srgb,var(--accent) 12%,transparent),transparent 38%),
    linear-gradient(135deg,var(--bg-card),var(--bg-card-alt));
}
.legal-hero-copy{display:flex;flex-direction:column;gap:12px;max-width:780px;}
.legal-hero h1{
  margin:0;font-size:clamp(32px,4.4vw,54px);font-weight:800;line-height:1.05;
  letter-spacing:-.045em;text-wrap:balance;
}
.legal-hero-copy > p:last-child{
  margin:0;max-width:620px;color:var(--text-secondary);font-size:15px;line-height:1.65;
}
.legal-meta{
  display:flex;flex-direction:column;gap:4px;padding:14px 17px;
  border:1px solid var(--border);border-radius:14px;background:color-mix(in srgb,var(--bg-card) 74%,transparent);
  white-space:nowrap;
}
.legal-meta span{
  color:var(--text-muted);font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;
}
.legal-meta strong{font-size:13px;font-weight:800;}
.legal-layout{
  display:grid;grid-template-columns:240px minmax(0,1fr);gap:clamp(26px,4vw,58px);align-items:start;
}
.legal-index{
  position:sticky;top:104px;display:flex;flex-direction:column;gap:4px;
  padding:16px;border:1px solid var(--border);border-radius:16px;background:var(--bg-card);
}
.legal-index > p{
  margin:0 8px 7px;color:var(--text-muted);font-size:10px;font-weight:800;
  letter-spacing:.08em;text-transform:uppercase;
}
.legal-index > a{
  display:flex;align-items:center;gap:9px;padding:9px 8px;border-radius:9px;
  color:var(--text-secondary);font-size:12.5px;font-weight:700;text-decoration:none;
}
.legal-index > a:hover{background:var(--bg-card-alt);color:var(--text-primary);}
.legal-index > a span{color:var(--accent);font-size:10px;font-weight:900;}
.legal-index .legal-back{
  margin-top:8px;padding-top:14px;border-top:1px solid var(--border);border-radius:0;
  color:var(--accent);
}
.legal-index .legal-back:hover{background:transparent;color:var(--text-primary);}
.legal-doc{display:flex;flex-direction:column;gap:32px;max-width:940px;min-width:0;}
.legal-section{
  scroll-margin-top:104px;display:flex;flex-direction:column;gap:14px;
  padding:clamp(24px,3.4vw,42px);border:1px solid var(--border);border-radius:22px;
  background:var(--bg-card);box-shadow:var(--shadow-sm);
}
.legal-kicker{
  margin:0;color:var(--accent);font-size:10.5px;font-weight:900;
  letter-spacing:.09em;text-transform:uppercase;
}
.legal-section h2{
  margin:0 0 4px;font-size:clamp(24px,2.6vw,34px);font-weight:800;
  line-height:1.15;letter-spacing:-.035em;
}
.legal-section h3{
  margin:14px 0 0;font-size:16px;font-weight:800;line-height:1.35;letter-spacing:-.01em;
}
.legal-section p,.legal-list{
  margin:0;color:var(--text-secondary);font-size:13.5px;line-height:1.75;
}
.legal-section strong{color:var(--text-primary);}
.legal-list{display:flex;flex-direction:column;gap:7px;padding-left:20px;}
.legal-list li{padding-left:3px;}
.legal-list li::marker{color:var(--accent);}
.legal-note{
  display:flex;align-items:flex-start;gap:11px;margin-top:10px;padding:15px 16px;
  border:1px solid color-mix(in srgb,var(--accent) 22%,var(--border));border-radius:13px;
  background:var(--accent-soft);
}
.legal-note .icon{flex-shrink:0;margin-top:1px;color:var(--accent);}
.legal-note p{color:var(--text-secondary);font-size:12.5px;line-height:1.6;}
@media(max-width:900px){
  .legal-layout{grid-template-columns:1fr;}
  .legal-index{position:relative;top:0;flex-direction:row;flex-wrap:wrap;align-items:center;}
  .legal-index > p{width:100%;margin-bottom:2px;}
  .legal-index .legal-back{margin-top:0;margin-left:auto;padding:9px 8px;border-top:0;}
}
@media(max-width:720px){
  .legal-hero{grid-template-columns:1fr;gap:20px;border-radius:18px;}
  .legal-meta{justify-self:start;}
  .legal-section{border-radius:16px;}
  .legal-index .legal-back{width:100%;margin-left:0;border-top:1px solid var(--border);}
}
</style>
