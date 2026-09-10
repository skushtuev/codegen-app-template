'use client'

import { useState } from 'react'
import { Badge, Group, Stack, Text, ThemeIcon } from '@mantine/core'
import { Ban, MailCheck, MailX, UserRound } from 'lucide-react'
import { useFormatter, useTranslations } from 'next-intl'

import { UserBanModal } from '../ban-modal'
import type { User } from '@modules/dashboard/models/users-api.interface'
import { DashboardPageHeader } from '@modules/dashboard/components/page-header'
import { DashboardStatusBadge } from '@modules/dashboard/components/status-badge'
import { DashboardTable } from '@modules/dashboard/components/table'
import { useUsersListQuery } from '@modules/dashboard/store/users-api'

type Props = {
    page: number
    perPage: number
    onChangePage: (page: number) => void
}

export function UsersList({ page, perPage, onChangePage }: Props) {
    const t = useTranslations('Dashboard.Users')
    const format = useFormatter()
    const listParams = { page, perPage }
    const [banTarget, setBanTarget] = useState<User | null>(null)
    const { data: usersList, isFetching } = useUsersListQuery(listParams)

    const formatDate = (value: string) =>
        format.dateTime(new Date(value), { day: '2-digit', month: '2-digit', year: 'numeric' })

    return (
        <Stack gap="xl">
            <DashboardPageHeader title={t('List.title')} />

            <DashboardTable<User>
                response={usersList}
                loading={isFetching}
                columns={[
                    {
                        header: t('Table.user'),
                        render: (user) => <UserCell user={user} />,
                    },
                    {
                        header: t('Table.language'),
                        render: (user) => (
                            <Badge variant="light" color="gray">
                                {user.language.toUpperCase()}
                            </Badge>
                        ),
                    },
                    {
                        header: t('Table.status'),
                        render: (user) => <DashboardStatusBadge status={user.bannedAt ? 'banned' : 'active'} />,
                    },
                    {
                        header: t('Table.lastLogin'),
                        render: (user) =>
                            user.lastLoginAt ? (
                                formatDate(user.lastLoginAt)
                            ) : (
                                <Text c="dimmed" size="sm">
                                    {t('Table.never')}
                                </Text>
                            ),
                    },
                    {
                        header: t('Table.createdAt'),
                        render: (user) => formatDate(user.createdAt),
                    },
                ]}
                rowMenuItems={(user) => {
                    const isBanned = Boolean(user.bannedAt)

                    return [
                        {
                            type: 'text',
                            label: user.banReason ?? user.email,
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

            <UserBanModal user={banTarget} listParams={listParams} onClose={() => setBanTarget(null)} />
        </Stack>
    )
}

function UserCell({ user }: { user: User }) {
    const t = useTranslations('Dashboard.Users')
    const VerifiedIcon = user.emailVerified ? MailCheck : MailX

    return (
        <Group gap="sm" wrap="nowrap">
            <ThemeIcon color="indigo" variant="light" radius="md" size={38}>
                <UserRound size={20} strokeWidth={1.9} />
            </ThemeIcon>
            <Stack gap={2}>
                <Text fw={700}>{user.name ?? user.email}</Text>
                <Group gap={6} wrap="nowrap">
                    <VerifiedIcon
                        size={14}
                        strokeWidth={2}
                        color={`var(--mantine-color-${user.emailVerified ? 'green' : 'orange'}-6)`}
                        aria-label={t(user.emailVerified ? 'Table.verified' : 'Table.unverified')}
                    />
                    <Text c="dimmed" size="sm">
                        {user.email}
                    </Text>
                </Group>
            </Stack>
        </Group>
    )
}
