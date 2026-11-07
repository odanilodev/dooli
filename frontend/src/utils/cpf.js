// CPF válido: 11 dígitos, não repetidos e com os dois dígitos verificadores corretos.
export function isValidCpf(value) {
  const cpf = String(value ?? '').replace(/\D/g, '');

  if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
    return false;
  }

  for (let length = 9; length <= 10; length += 1) {
    let sum = 0;

    for (let i = 0; i < length; i += 1) {
      sum += Number(cpf[i]) * (length + 1 - i);
    }

    if (Number(cpf[length]) !== ((sum * 10) % 11) % 10) {
      return false;
    }
  }

  return true;
}
