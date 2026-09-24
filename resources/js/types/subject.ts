// A workspace member who can teach (an owner or a tutor).
export type Teacher = {
    id: number;
    name: string;
};

export type Subject = {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    // Whole rupiah.
    price: number;
    teachers: Teacher[];
};
