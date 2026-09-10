import { createApi } from '@reduxjs/toolkit/query/react'
import { baseFetchQuery } from '@modules/shared/helpers/rtk-query'

import type { BanUserParams, UnbanUserParams, UsersListResponse } from '../models/users-api.interface'
import type { PaginationRequest } from '@modules/shared/models/pagination.interface'

export const usersApi = createApi({
    reducerPath: 'users/api',
    baseQuery: baseFetchQuery('/users'),
    tagTypes: ['Users'],
    endpoints: (builder) => ({
        usersList: builder.query<UsersListResponse, PaginationRequest>({
            query: (params) => ({
                url: '/list',
                method: 'GET',
                params,
            }),
            providesTags: ['Users'],
        }),
        banUser: builder.mutation<void, BanUserParams>({
            query: ({ id, reason }) => ({
                url: `/ban/${id}`,
                method: 'POST',
                body: { reason },
            }),
        }),
        unbanUser: builder.mutation<void, UnbanUserParams>({
            query: ({ id }) => ({
                url: `/unban/${id}`,
                method: 'POST',
            }),
        }),
    }),
})

export const { useUsersListQuery, useBanUserMutation, useUnbanUserMutation } = usersApi
