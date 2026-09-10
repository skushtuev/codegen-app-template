import type { PaginationResponse } from '@modules/shared/models/pagination.interface'

export type UserLanguage = 'ru' | 'en'

export type User = {
    id: string
    identityId: string
    email: string
    name: string | null
    language: UserLanguage
    emailVerified: boolean
    lastLoginAt: string | null
    bannedAt: string | null
    banReason: string | null
    createdAt: string
}

export type UsersListResponse = PaginationResponse<User>

export type BanUserParams = {
    id: string
    reason: string | null
}

export type UnbanUserParams = {
    id: string
}
