import { combineReducers, configureStore } from '@reduxjs/toolkit'
import { authApi } from '@modules/auth'
import { accountApi, adminUsersApi, mediaLibraryApi, usersApi } from '@modules/dashboard'

const rootReducer = combineReducers({
    // auth
    [authApi.reducerPath]: authApi.reducer,
    [accountApi.reducerPath]: accountApi.reducer,
    [adminUsersApi.reducerPath]: adminUsersApi.reducer,
    [mediaLibraryApi.reducerPath]: mediaLibraryApi.reducer,
    [usersApi.reducerPath]: usersApi.reducer,
})

export const setupStore = () =>
    configureStore({
        reducer: rootReducer,
        middleware: (getDefaultMiddleware) => {
            return getDefaultMiddleware().concat(
                authApi.middleware,
                accountApi.middleware,
                adminUsersApi.middleware,
                mediaLibraryApi.middleware,
                usersApi.middleware
            )
        },
    })

export type RootState = ReturnType<typeof rootReducer>
export type AppStore = ReturnType<typeof setupStore>
export type AppDispatch = AppStore['dispatch']
