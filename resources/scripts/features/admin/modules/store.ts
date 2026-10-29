import { defineStore } from 'pinia'
import { moduleService } from '../../../api/services/module.service'
import type { Module } from '../../../types/domain/module'
import type {
  MarketplacePairingCode,
  MarketplacePairingStatus,
  ModuleDetailResponse,
  ModuleInstallPayload,
  ModuleUninstallPayload,
} from '../../../api/services/module.service'

export type { ModuleDetailResponse, ModuleDetailMeta } from '../../../api/services/module.service'

export interface InstallationStep {
  translationKey: string
  stepUrl: string
  time: string | null
  started: boolean
  completed: boolean
}

export interface ModuleState {
  currentModule: ModuleDetailResponse | null
  modules: Module[]
  marketplacePairing: MarketplacePairingStatus | null
  enableModules: string[]
}

export const useModuleStore = defineStore('modules', {
  state: (): ModuleState => ({
    currentModule: null,
    modules: [],
    marketplacePairing: null,
    enableModules: [],
  }),

  getters: {
    salesTaxUSEnabled: (state): boolean => state.enableModules.includes('SalesTaxUS'),
    installedModules: (state): Module[] => state.modules.filter((m) => m.installed),
  },

  actions: {
    async fetchModules(): Promise<void> {
      const response = await moduleService.list()
      this.modules = response.data
    },

    async fetchModule(slug: string): Promise<ModuleDetailResponse> {
      const response = await moduleService.get(slug)
      this.currentModule = response
      return response
    },

    async fetchMarketplacePairing(): Promise<MarketplacePairingStatus> {
      const response = await moduleService.pairingStatus()
      this.marketplacePairing = response
      return response
    },

    async startMarketplacePairing(): Promise<MarketplacePairingCode> {
      return moduleService.startPairing()
    },

    async pollMarketplacePairing(): Promise<{ status: 'pending' | 'paired' }> {
      const response = await moduleService.pollPairing()
      if (response.status === 'paired') await this.fetchMarketplacePairing()
      return response
    },

    async disconnectMarketplace(): Promise<void> {
      await moduleService.disconnectMarketplace()
      this.marketplacePairing = { paired: false, expired: false, paired_at: null }
    },

    async disableModule(moduleName: string): Promise<{ success: boolean }> {
      return moduleService.disable(moduleName)
    },

    async enableModule(moduleName: string): Promise<{ success: boolean }> {
      return moduleService.enable(moduleName)
    },

    async installModule(
      payload: ModuleInstallPayload,
      onStepUpdate?: (step: InstallationStep) => void,
    ): Promise<boolean> {
      const step: InstallationStep = {
        translationKey: 'modules.completing_installation',
        stepUrl: '/api/v1/modules/install',
        time: null,
        started: true,
        completed: false,
      }
      onStepUpdate?.(step)

      try {
        const response = await moduleService.install(payload)
        step.completed = true
        onStepUpdate?.(step)
        return response.success
      } catch (err: unknown) {
        step.completed = true
        onStepUpdate?.(step)
        const { useNotificationStore } = await import('@/scripts/stores/notification.store')
        useNotificationStore().showNotification({
          type: 'error',
          message: err instanceof Error ? err.message : 'Module installation failed',
        })
        return false
      }
    },

    async uninstallModule(
      moduleName: string,
      payload: ModuleUninstallPayload,
    ): Promise<{ success: boolean; error?: string }> {
      return moduleService.uninstall(moduleName, payload)
    },
  },
})

export type ModuleStore = ReturnType<typeof useModuleStore>
