import type { Role } from '@/types';

// Owners and tutors teach in a workspace; students study there. (The server's Role::teaching().)
export function teaches(role: Role): boolean {
    return role === 'owner' || role === 'tutor';
}
