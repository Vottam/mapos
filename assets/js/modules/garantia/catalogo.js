const DEFAULT_DIAS = '90';

export default function iniciar(raiz) {
    const dias = raiz.querySelector('[name="garantia_dias"]');
    const avisoZero = raiz.querySelector('[data-garantia-zero-aviso]');
    if (!dias) {
        return;
    }

    const atualizarAviso = () => {
        if (avisoZero) {
            avisoZero.hidden = dias.value !== '0';
        }
    };

    dias.addEventListener('input', atualizarAviso);
    atualizarAviso();
}