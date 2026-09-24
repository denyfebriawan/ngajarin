const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

// 150000 -> "Rp 150.000"
export function formatRupiah(amount: number): string {
    return rupiah.format(amount);
}

const shortDate = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

// "2026-12-24" -> "24 Dec 2026". Built from the parts on purpose: new Date("2026-12-24") means
// midnight UTC, which is still 23 Dec in timezones west of UTC.
export function formatDate(isoDate: string): string {
    const [year, month, day] = isoDate.split('-').map(Number);

    return shortDate.format(new Date(year, month - 1, day));
}

// 90 -> "1 h 30 min", 60 -> "1 h", 45 -> "45 min"
export function formatDuration(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return [hours > 0 && `${hours} h`, rest > 0 && `${rest} min`]
        .filter(Boolean)
        .join(' ');
}
