'use client'

import { useState } from 'react'
import { Badge, Group, Stack, Text, ThemeIcon } from '@mantine/core'
import { Ban, KeyRound, ShieldCheck, UserCog } from 'lucide-react'
import { useFormatter, useTranslations } from 'next-intl'

import { AdminUserBanConfirm } from '../ban-confirm'
import { ChangeRoleModal } from '../change-role'
import { ChangePasswordModal } from '../change-password'
import { AdminUserRoleBadge } from '../role-badge'
import type { AdminUser, AdminUserRoleOption } from '@modules/dashboard/models/admin-users-api.interface'
import { DashboardPageHeader } from '@modules/dashboard/components/page-header'
import { DashboardStatusBadge } from '@modules/dashboard/components/status-badge'
import { DashboardTable } from '@modules/dashboard/components/table'
import { useAdminUsersListQuery } from '@modules/dashboard/store/admin-users-api'
import { useMustUser, type IAccount } from '@modules/auth'

type Props = {
    page: number
    perPage: number
    roles: AdminUserRoleOption[]
    loadingRoles: boolean
    onChangePage: (page: number) => void
    onCreateClick: () => void
}

export function AdminUsersList({ page, perPage, roles, loadingRoles, onChangePage, onCreateClick }: Props) {
    const t = useTranslations('Dashboard.AdminUsers')
    const { user: currentUser } = useMustUser()
    const format = useFormatter()
    const listParams = { page, perPage }
    const [passwordTarget, setPasswordTarget] = useState<AdminUser | null>(null)
    const [roleTarget, setRoleTarget] = useState<AdminUser | null>(null)
    const [banTarget, setBanTarget] = useState<AdminUser | null>(null)
    const { data: usersList, isFetching: isUsersListFetching } = useAdminUsersListQuery(listParams)

    return (
        <Stack gap="xl">
            <DashboardPageHeader
                title={t('List.title')}
                createAction={{
                    type: 'button',
                    label: t('List.createButton'),
                    onClick: onCreateClick,
                }}
            />

            <DashboardTable<AdminUser>
                response={usersList}
                loading={isUsersListFetching}
                columns={[
                    {
                        header: t('Table.user'),
                        render: (user) => <AdminUserCell user={user} currentUser={currentUser} />,
                    },
                    {
                        header: t('Table.role'),
                        render: (user) => <AdminUserRoleBadge role={user.role} />,
                    },
                    {
                        header: t('Table.status'),
                        render: (user) => <DashboardStatusBadge status={user.bannedAt ? 'banned' : 'active'} />,
                    },
                    {
                        header: t('Table.createdAt'),
                        render: (user) =>
                            format.dateTime(new Date(user.createdAt), {
                                day: '2-digit',
                                month: '2-digit',
                                year: 'numeric',
                            }),
                    },
                ]}
                rowMenuItems={(user) => {
                    const isBanned = Boolean(user.bannedAt)

                    if (user.id === currentUser.id) {
                        return []
                    }

                    return [
                        {
                            type: 'text',
                            label: user.email,
                        },
                        {
                            type: 'divider',
                        },
                        {
                            label: t('Table.changePassword'),
                            icon: KeyRound,
                            onClick: () => setPasswordTarget(user),
                        },
                        {
                            label: t('Table.changeRole'),
                            icon: ShieldCheck,
                            onClick: () => setRoleTarget(user),
                        },
                        {
                            type: 'divider',
                        },
                        {
                            label: isBanned ? t('Table.unban') : t('Table.ban'),
                            icon: Ban,
                            color: isBanned ? 'green' : 'red',
                            onClick: () => setBanTarget(user),
                        },
                    ]
                }}
                onChangePage={onChangePage}
            />

            <ChangePasswordModal
                opened={Boolean(passwordTarget)}
                user={passwordTarget}
                onClose={() => setPasswordTarget(null)}
            />

            <ChangeRoleModal
                opened={Boolean(roleTarget)}
                user={roleTarget}
                roles={roles}
                loadingRoles={loadingRoles}
                listParams={listParams}
                onClose={() => setRoleTarget(null)}
            />

            <AdminUserBanConfirm user={banTarget} listParams={listParams} onClose={() => setBanTarget(null)} />
        </Stack>
    )
}

function AdminUserCell({ user, currentUser }: { user: AdminUser; currentUser: IAccount }) {
    const t = useTranslations('Dashboard.AdminUsers')

    return (
        <Group gap="sm" wrap="nowrap">
            <ThemeIcon color="indigo" variant="light" radius="md" size={38}>
                <UserCog size={20} strokeWidth={1.9} />
            </ThemeIcon>
            <Stack gap={2}>
                <Group gap={6} wrap="nowrap">
                    <Text fw={700}>{getAdminUserName(user)}</Text>
                    {user.id === currentUser.id && (
                        <Badge size="xs" color="indigo" variant="light">
                            {t('Table.me')}
                        </Badge>
                    )}
                </Group>
                <Text c="dimmed" size="sm">
                    {user.email}
                </Text>
            </Stack>
        </Group>
    )
}

function getAdminUserName(user: AdminUser): string {
    return [user.firstName, user.lastName].filter(Boolean).join(' ')
}
