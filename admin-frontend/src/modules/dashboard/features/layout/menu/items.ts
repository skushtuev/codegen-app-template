import { FolderTree, Images, UserRound, UsersRound, type LucideIcon } from 'lucide-react'
import type { IAdminPermissions } from '@modules/auth'

export type DashboardMenuSubItem = {
    label: string
    link: string
    can?: keyof IAdminPermissions
}

export type DashboardMenuItem = {
    icon: LucideIcon
    label: string
    link?: string
    can?: keyof IAdminPermissions
    children?: DashboardMenuSubItem[]
}

export const dashboardMenuItems: DashboardMenuItem[] = [
    {
        icon: UsersRound,
        label: 'adminUsers',
        link: '/admin-users',
        can: 'adminUsers',
    },
    {
        icon: UserRound,
        label: 'users',
        link: '/users',
        can: 'users',
    },
    {
        icon: Images,
        label: 'mediaLibrary',
        link: '/media-library',
        can: 'adminMedia',
    },
    {
        icon: FolderTree,
        label: 'Каталог',
        children: [
            {
                label: 'Теги',
                link: '/catalog/tags',
            },
        ],
    },
]
