import { client } from '../client'
import { API } from '../endpoints'
import type { ApiResponse } from '@/scripts/types/api'
import type { Module } from '@/scripts/types/domain/module'

export type { Module } from '@/scripts/types/domain/module'

export interface MarketplacePairingStatus {
  paired: boolean
  expired: boolean
  paired_at: string | null
}

export interface MarketplacePairingCode {
  device_code: string
  user_code: string | null
  verification_uri: string | null
  verification_uri_complete: string | null
  expires_in: number
  interval: number
}

export interface ModuleDetailMeta {
  modules: Module[]
}

export interface ModuleInstallPayload {
  slug: string
  version: string
  channel?: 'stable' | 'insider'
}

export interface ModuleUninstallPayload {
  remove_data: boolean
  confirmation?: string
}

export interface ModuleDetailResponse {
  data: Module
  meta: ModuleDetailMeta
}

export const moduleService = {
  async list(): Promise<ApiResponse<Module[]>> {
    const { data } = await client.get(API.MODULES)
    return data
  },

  async get(module: string): Promise<ModuleDetailResponse> {
    const { data } = await client.get(`${API.MODULES}/${module}`)
    return data
  },

  async pairingStatus(): Promise<MarketplacePairingStatus> {
    const { data } = await client.get(API.MODULES_PAIRING)
    return data
  },

  async startPairing(): Promise<MarketplacePairingCode> {
    const { data } = await client.post(`${API.MODULES_PAIRING}/start`)
    return data
  },

  async pollPairing(): Promise<{ status: 'pending' | 'paired' }> {
    const { data } = await client.post(`${API.MODULES_PAIRING}/poll`)
    return data
  },

  async disconnectMarketplace(): Promise<{ success: boolean }> {
    const { data } = await client.delete(API.MODULES_PAIRING)
    return data
  },

  async enable(module: string): Promise<{ success: boolean }> {
    const { data } = await client.post(`${API.MODULES}/${module}/enable`)
    return data
  },

  async disable(module: string): Promise<{ success: boolean }> {
    const { data } = await client.post(`${API.MODULES}/${module}/disable`)
    return data
  },

  async install(payload: ModuleInstallPayload): Promise<{ success: boolean; error?: string }> {
    const { data } = await client.post(API.MODULES_INSTALL, payload)
    return data
  },

  async uninstall(module: string, payload: ModuleUninstallPayload): Promise<{ success: boolean; error?: string }> {
    const { data } = await client.post(`${API.MODULES_UNINSTALL}/${module}/uninstall`, payload)
    return data
  },
}
