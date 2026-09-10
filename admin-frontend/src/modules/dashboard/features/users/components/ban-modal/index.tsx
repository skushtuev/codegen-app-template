'use client'

import { Button, Group, Modal, Stack, Text, Textarea } from '@mantine/core'
import { useForm } from '@mantine/form'
import { notifications } from '@mantine/notifications'
import { useEffect } from 'react'
import { useTranslations } from 'next-intl'

import type { User } from '@modules/dashboard/models/users-api.interface'
import { usersApi, useBanUserMutation, useUnbanUserMutation } from '@modules/dashboard/store/users-api'
import type { PaginationRequest } from '@modules/shared/models/pagination.interface'
import { useAppDispatch } from '@modules/shared/hooks/use-app-dispatch'

type Props = {
    user: User | null
    listParams: PaginationRequest
    onClose: () => void
}

export function UserBanModal({ user, listParams, onClose }: Props) {
    const t = useTranslations('Dashboard.Users.Ban')
    const notificationT = useTranslations('Shared.Notification')
    const dispatch = useAppDispatch()
    const [banUser, { isLoading: isBanSubmitting }] = useBanUserMutation()
    const [unbanUser, { isLoading: isUnbanSubmitting }] = useUnbanUserMutation()
    const isBanned = Boolean(user?.bannedAt)
    const loading = isBanSubmitting || isUnbanSubmitting

    const form = useForm({
        initialValues: { reason: '' },
        transformValues: (values) => ({ reason: values.reason.trim() || null }),
    })

    // The modal stays mounted between targets, so clear the previous reason.
    useEffect(() => {
        form.reset()
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [user?.id])

    const handleSubmit = async ({ reason }: { reason: string | null }) => {
        if (!user) {
            return
        }

        try {
            if (isBanned) {
                await unbanUser({ id: user.id }).unwrap()
                updateCachedUser(dispatch, listParams, user.id, null, null)
            } else {
                await banUser({ id: user.id, reason }).unwrap()
                updateCachedUser(dispatch, listParams, user.id, new Date().toISOString(), reason)
            }

            notifications.show({
                color: isBanned ? 'teal' : 'red',
                title: isBanned ? t('unbanSuccessTitle') : t('banSuccessTitle'),
                message: isBanned ? t('unbanSuccessMessage') : t('banSuccessMessage'),
            })
            onClose()
        } catch {
            notifications.show({
                color: 'red',
                title: notificationT('internalErrorTitle'),
                message: notificationT('internalErrorMessage'),
            })
        }
    }

    return (
        <Modal opened={Boolean(user)} onClose={onClose} title={isBanned ? t('unbanTitle') : t('banTitle')} size="sm">
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <Stack gap="lg">
                    <Text c="dark.9" size="sm">
                        {user ? t(isBanned ? 'unbanMessage' : 'banMessage', { email: user.email }) : ''}
                    </Text>

                    {!isBanned && (
                        <Textarea
                            label={t('reasonLabel')}
                            placeholder={t('reasonPlaceholder')}
                            autosize
                            minRows={2}
                            maxRows={4}
                            maxLength={500}
                            {...form.getInputProps('reason')}
                        />
                    )}

                    <Group justify="flex-end">
                        <Button variant="default" onClick={onClose} disabled={loading}>
                            {t('cancel')}
                        </Button>
                        <Button type="submit" color={isBanned ? 'teal' : 'red'} loading={loading}>
                            {isBanned ? t('unbanConfirm') : t('banConfirm')}
                        </Button>
                    </Group>
                </Stack>
            </form>
        </Modal>
    )
}

function updateCachedUser(
    dispatch: ReturnType<typeof useAppDispatch>,
    listParams: PaginationRequest,
    id: string,
    bannedAt: string | null,
    banReason: string | null
) {
    dispatch(
        usersApi.util.updateQueryData('usersList', listParams, (draft) => {
            const user = draft.data.find((item) => item.id === id)

            if (user) {
                user.bannedAt = bannedAt
                user.banReason = banReason
            }
        })
    )
}
