'use client'

import { useState } from 'react'

import { UsersList } from './components/list'

const PER_PAGE = 10

export function Users() {
    const [page, setPage] = useState(1)

    return <UsersList page={page} perPage={PER_PAGE} onChangePage={setPage} />
}
