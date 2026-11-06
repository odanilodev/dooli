const SDK_URL = 'https://sdk.mercadopago.com/js/v2';

let sdkPromise = null;

/**
 * Carrega o SDK MP.js do Mercado Pago uma única vez e devolve o construtor MercadoPago.
 * Usado no checkout transparente de cartão (os dados do cartão vão direto do navegador
 * para o Mercado Pago, que devolve um token; o servidor nunca vê o número do cartão).
 */
export function loadMercadoPago() {
  if (typeof window === 'undefined') {
    return Promise.reject(new Error('Ambiente sem navegador.'));
  }

  if (window.MercadoPago) {
    return Promise.resolve(window.MercadoPago);
  }

  if (!sdkPromise) {
    sdkPromise = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = SDK_URL;
      script.async = true;
      script.onload = () => resolve(window.MercadoPago);
      script.onerror = () => {
        sdkPromise = null;
        script.remove();
        reject(new Error('Não foi possível carregar o formulário de pagamento. Verifique sua conexão.'));
      };
      document.head.appendChild(script);
    });
  }

  return sdkPromise;
}
