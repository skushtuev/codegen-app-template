import { Badge, type MantineColor } from '@mantine/core'
import { useTranslations } from 'next-intl'

export type DashboardStatus = 'active' | 'banned'

const statusColors: Record<DashboardStatus, MantineColor> = {
    active: 'green',
    banned: 'red',
}

type Props = {
    status: DashboardStatus
}

export function DashboardStatusBadge({ status }: Props) {
    const t = useTranslations('Dashboard.Statuses')

    return (
        <Badge color={statusColors[status]} variant="light">
            {t(status)}
        </Badge>
    )
}
