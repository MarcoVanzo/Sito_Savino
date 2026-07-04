/**
 * Composable per formattare i prezzi in formato EUR italiano.
 *
 * Utilizzo:
 *   const { formatPrice } = useFormatPrice();
 *   formatPrice(29.99); // → "29,99 €"
 */
export function useFormatPrice() {
    const formatPrice = (price) => {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR',
        }).format(price);
    };

    return { formatPrice };
}
