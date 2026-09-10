export enum AdminUserRole {
    Admin = 'admin',
    Editor = 'editor',
}

export interface IAccount {
    id: string
    email: string
    firstName: string
    lastName: string | null
    role: string
}

export interface IAdminPermissions {
    adminUsers: boolean
    adminMedia: boolean
    users: boolean
}

export interface IWhoami {
    account: IAccount
    can: IAdminPermissions
}
