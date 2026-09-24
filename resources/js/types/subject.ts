export type Subject = {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    // Whole rupiah.
    price: number;
};
