const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

// 150000 -> "Rp 150.000"
export function formatRupiah(amount: number): string {
    return rupiah.format(amount);
}

// 90 -> "1 h 30 min", 60 -> "1 h", 45 -> "45 min"
export function formatDuration(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return [hours > 0 && `${hours} h`, rest > 0 && `${rest} min`]
        .filter(Boolean)
        .join(' ');
}
