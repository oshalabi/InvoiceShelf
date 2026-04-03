<template>
  <CategoryModal />

  <BasePage class="relative">
    <form action="" @submit.prevent="submitForm">
      <!-- Page Header -->
      <BasePageHeader :title="pageTitle" class="mb-5">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem
            :title="$t('general.home')"
            to="/admin/dashboard"
          />

          <BaseBreadcrumbItem
            :title="$t('expenses.expense', 2)"
            to="/admin/expenses"
          />

          <BaseBreadcrumbItem :title="pageTitle" to="#" active />
        </BaseBreadcrumb>

        <template #actions>
          <BaseButton
            v-if="isEdit && expenseStore.currentExpense.attachment_receipt_url"
            :href="receiptDownloadUrl"
            tag="a"
            variant="primary-outline"
            type="button"
            class="mr-2"
          >
            <template #left="slotProps">
              <BaseIcon name="DownloadIcon" :class="slotProps.class" />
            </template>
            {{ $t('expenses.download_receipt') }}
          </BaseButton>

          <div class="hidden md:block">
            <BaseButton
              :loading="isSaving"
              :content-loading="isFetchingInitialData"
              :disabled="isSaving || expenseStore.ocrPreview.isRunning"
              variant="primary"
              type="submit"
            >
              <template #left="slotProps">
                <BaseIcon
                  v-if="!isSaving"
                  name="ArrowDownOnSquareIcon"
                  :class="slotProps.class"
                />
              </template>
              {{
                isEdit
                  ? $t('expenses.update_expense')
                  : $t('expenses.save_expense')
              }}
            </BaseButton>
          </div>
        </template>
      </BasePageHeader>

      <BaseCard>
        <BaseInputGrid>
          <BaseInputGroup
            :label="$t('expenses.category')"
            :error="
              v$.currentExpense.expense_category_id.$error &&
              v$.currentExpense.expense_category_id.$errors[0].$message
            "
            :content-loading="isFetchingInitialData"
            required
          >
            <BaseMultiselect
              v-model="expenseStore.currentExpense.expense_category_id"
              :content-loading="isFetchingInitialData"
              value-prop="id"
              label="name"
              track-by="id"
              :options="searchCategory"
              v-if="!isFetchingInitialData"
              :filter-results="false"
              resolve-on-load
              :delay="500"
              searchable
              :invalid="v$.currentExpense.expense_category_id.$error"
              :placeholder="$t('expenses.categories.select_a_category')"
              @input="v$.currentExpense.expense_category_id.$touch()"
            >
              <template #action>
                <BaseSelectAction @click="openCategoryModal">
                  <BaseIcon
                    name="PlusIcon"
                    class="h-4 mr-2 -ml-2 text-center text-primary-400"
                  />
                  {{ $t('settings.expense_category.add_new_category') }}
                </BaseSelectAction>
              </template>
            </BaseMultiselect>
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('expenses.expense_date')"
            :help-text="getOcrFlaggedHelpText('expense_date')"
            :error="
              v$.currentExpense.expense_date.$error &&
              v$.currentExpense.expense_date.$errors[0].$message
            "
            :content-loading="isFetchingInitialData"
            required
          >
            <BaseDatePicker
              v-model="expenseStore.currentExpense.expense_date"
              :content-loading="isFetchingInitialData"
              :calendar-button="true"
              :invalid="v$.currentExpense.expense_date.$error"
              @input="v$.currentExpense.expense_date.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('expenses.expense_number')"
            :help-text="getOcrFlaggedHelpText('expense_number')"
            :content-loading="isFetchingInitialData"
          >
            <BaseInput
              v-model="expenseStore.currentExpense.expense_number"
              :content-loading="isFetchingInitialData"
              type="text"
              name="expense_number"
              :placeholder="$t('expenses.expense_number_placeholder')"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('expenses.amount')"
            :help-text="getOcrFlaggedHelpText('amount')"
            :error="
              v$.currentExpense.amount.$error &&
              v$.currentExpense.amount.$errors[0].$message
            "
            :content-loading="isFetchingInitialData"
            required
          >
            <BaseMoney
              :key="expenseStore.currentExpense.selectedCurrency"
              v-model="amountData"
              class="focus:border focus:border-solid focus:border-primary-500"
              :invalid="v$.currentExpense.amount.$error"
              :currency="expenseStore.currentExpense.selectedCurrency"
              @input="v$.currentExpense.amount.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('expenses.currency')"
            :content-loading="isFetchingInitialData"
            :help-text="getOcrFlaggedHelpText('currency_id')"
            :error="
              v$.currentExpense.currency_id.$error &&
              v$.currentExpense.currency_id.$errors[0].$message
            "
            required
          >
            <BaseMultiselect
              v-model="expenseStore.currentExpense.currency_id"
              value-prop="id"
              label="name"
              track-by="name"
              :content-loading="isFetchingInitialData"
              :options="globalStore.currencies"
              searchable
              :can-deselect="false"
              :placeholder="$t('customers.select_currency')"
              :invalid="v$.currentExpense.currency_id.$error"
              class="w-full"
              @update:modelValue="onCurrencyChange"
            >
            </BaseMultiselect>
          </BaseInputGroup>

          <!-- Exchange rate converter -->
          <ExchangeRateConverter
            :store="expenseStore"
            store-prop="currentExpense"
            :v="v$.currentExpense"
            :is-loading="isFetchingInitialData"
            :is-edit="isEdit"
            :customer-currency="expenseStore.currentExpense.currency_id"
          />

          <BaseInputGroup
            :content-loading="isFetchingInitialData"
            :label="$t('expenses.customer')"
          >
            <BaseMultiselect
              v-model="expenseStore.currentExpense.customer_id"
              :content-loading="isFetchingInitialData"
              value-prop="id"
              label="name"
              track-by="id"
              :options="searchCustomer"
              v-if="!isFetchingInitialData"
              :filter-results="false"
              resolve-on-load
              :delay="500"
              searchable
              :placeholder="$t('customers.select_a_customer')"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :content-loading="isFetchingInitialData"
            :label="$t('payments.payment_mode')"
          >
            <BaseMultiselect
              v-model="expenseStore.currentExpense.payment_method_id"
              :content-loading="isFetchingInitialData"
              label="name"
              value-prop="id"
              track-by="name"
              :options="expenseStore.paymentModes"
              :placeholder="$t('payments.select_payment_mode')"
              searchable
            >
              <!-- <template #action>
                <BaseSelectAction @click="addPaymentMode">
                  <BaseIcon
                    name="PlusIcon"
                    class="h-4 mr-2 -ml-2 text-center text-primary-400"
                  />
                  {{ $t('settings.payment_modes.add_payment_mode') }}
                </BaseSelectAction>
              </template> -->
            </BaseMultiselect>
          </BaseInputGroup>

        </BaseInputGrid>

        <BaseInputGrid class="mt-4">
          <BaseInputGroup
            :content-loading="isFetchingInitialData"
            :label="$t('expenses.note')"
            :error="
              v$.currentExpense.notes.$error &&
              v$.currentExpense.notes.$errors[0].$message
            "
          >
            <BaseTextarea
              v-model="expenseStore.currentExpense.notes"
              :content-loading="isFetchingInitialData"
              :row="4"
              rows="4"
              @input="v$.currentExpense.notes.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('expenses.receipt')"
            :help-text="receiptHelpText"
          >
            <BaseFileUploader
              v-model="expenseStore.currentExpense.receiptFiles"
              accept="image/*,.doc,.docx,.pdf,.csv,.xlsx,.xls"
              @change="onFileInputChange"
              @remove="onFileInputRemove"
            />

            <div
              v-if="expenseStore.ocrPreview.isRunning"
              class="mt-3 rounded-md border border-blue-100 bg-blue-50 px-4 py-3"
            >
              <div class="flex items-center text-sm text-blue-700">
                <BaseSpinner class="mr-2 h-4 w-4" />
                {{ $t('expenses.ocr.processing') }}
              </div>
            </div>

            <div
              v-else-if="showOcrSummary"
              :class="ocrSummaryClasses"
              class="mt-3 rounded-md border px-4 py-4"
            >
              <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                  <p class="text-sm font-medium">
                    {{ ocrSummaryTitle }}
                  </p>
                  <p v-if="expenseStore.ocrPreview.message" class="mt-1 text-sm">
                    {{ expenseStore.ocrPreview.message }}
                  </p>
                </div>

                <BaseButton
                  v-if="canRetryOcr"
                  variant="primary-outline"
                  type="button"
                  @click="retryOcrPreview"
                >
                  <template #left="slotProps">
                    <BaseIcon name="ArrowPathIcon" :class="slotProps.class" />
                  </template>
                  {{ $t('expenses.ocr.retry') }}
                </BaseButton>
              </div>

              <ul v-if="ocrSummaryLines.length" class="mt-3 space-y-2 text-sm">
                <li v-for="line in ocrSummaryLines" :key="line">
                  {{ line }}
                </li>
              </ul>
            </div>
          </BaseInputGroup>

          <!-- Expense Custom Fields -->
          <ExpenseCustomFields
            :is-edit="isEdit"
            class="col-span-2"
            :is-loading="isFetchingInitialData"
            type="Expense"
            :store="expenseStore"
            store-prop="currentExpense"
            :custom-field-scope="expenseValidationScope"
          />

          <div class="block md:hidden">
            <BaseButton
              :loading="isSaving"
              :tabindex="6"
              variant="primary"
              type="submit"
              :disabled="isSaving || expenseStore.ocrPreview.isRunning"
              class="flex justify-center w-full"
            >
              <template #left="slotProps">
                <BaseIcon
                  v-if="!isSaving"
                  name="ArrowDownOnSquareIcon"
                  :class="slotProps.class"
                />
              </template>
              {{
                isEdit
                  ? $t('expenses.update_expense')
                  : $t('expenses.save_expense')
              }}
            </BaseButton>
          </div>
        </BaseInputGrid>

      </BaseCard>
    </form>
  </BasePage>
</template>

<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  required,
  minValue,
  maxLength,
  helpers,
  requiredIf,
  decimal,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useExpenseStore } from '@/scripts/admin/stores/expense'
import { useCategoryStore } from '@/scripts/admin/stores/category'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useCustomerStore } from '@/scripts/admin/stores/customer'
import { useCustomFieldStore } from '@/scripts/admin/stores/custom-field'
import { useModalStore } from '@/scripts/stores/modal'
import { useNotificationStore } from '@/scripts/stores/notification'
import ExpenseCustomFields from '@/scripts/admin/components/custom-fields/CreateCustomFields.vue'
import CategoryModal from '@/scripts/admin/components/modal-components/CategoryModal.vue'
import ExchangeRateConverter from '@/scripts/admin/components/estimate-invoice-common/ExchangeRateConverter.vue'
import { useGlobalStore } from '@/scripts/admin/stores/global'

const customerStore = useCustomerStore()
const companyStore = useCompanyStore()
const expenseStore = useExpenseStore()
const categoryStore = useCategoryStore()
const customFieldStore = useCustomFieldStore()
const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const globalStore = useGlobalStore()

let isSaving = ref(false)
let isFetchingInitialData = ref(false)
const expenseValidationScope = 'newExpense'
const isAttachmentReceiptRemoved = ref(false)

const rules = computed(() => {
  return {
    currentExpense: {
      expense_category_id: {
        required: helpers.withMessage(t('validation.required'), required),
      },
      expense_date: {
        required: helpers.withMessage(t('validation.required'), required),
      },

      amount: {
        required: helpers.withMessage(t('validation.required'), required),
        minValue: helpers.withMessage(
          t('validation.price_minvalue'),
          minValue(0.1)
        ),
        maxLength: helpers.withMessage(
          t('validation.price_maxlength'),
          maxLength(20)
        ),
      },

      notes: {
        maxLength: helpers.withMessage(
          t('validation.description_maxlength'),
          maxLength(65000)
        ),
      },
      currency_id: {
        required: helpers.withMessage(t('validation.required'), required),
      },
      exchange_rate: {
        required: requiredIf(function () {
          helpers.withMessage(t('validation.required'), required)
          return expenseStore.showExchangeRate
        }),
        decimal: helpers.withMessage(
          t('validation.valid_exchange_rate'),
          decimal
        ),
      },
    },
  }
})

const v$ = useVuelidate(rules, expenseStore, {
  $scope: expenseValidationScope,
})

const amountData = computed({
  get: () => expenseStore.currentExpense.amount / 100,
  set: (value) => {
    expenseStore.currentExpense.amount = Math.round(value * 100)
  },
})

const isEdit = computed(() => route.name === 'expenses.edit')

const pageTitle = computed(() =>
  isEdit.value ? t('expenses.edit_expense') : t('expenses.new_expense')
)

const receiptDownloadUrl = computed(() =>
  isEdit.value ? `/reports/expenses/${route.params.id}/download-receipt` : ''
)

const isOcrEnabled = computed(() => {
  return (companyStore.selectedCompanySettings.ocr_expense_enabled || 'NO') === 'YES'
})

const receiptHelpText = computed(() => {
  if (!isOcrEnabled.value) {
    return null
  }

  return t('expenses.ocr.supported_file_types')
})

const ocrSummaryClasses = computed(() => {
  if (expenseStore.ocrPreview.status === 'failed') {
    return 'border-red-200 bg-red-50 text-red-700'
  }

  return 'border-gray-200 bg-gray-50 text-gray-600'
})

const showOcrSummary = computed(() => {
  return (
    !!expenseStore.ocrPreview.status &&
    !expenseStore.ocrPreview.isRunning &&
    (
      !!expenseStore.ocrPreview.message ||
      Object.keys(expenseStore.ocrPreview.flaggedFields).length > 0 ||
      Object.keys(expenseStore.ocrPreview.unmappedFields).length > 0 ||
      Object.keys(expenseStore.ocrPreview.mappedFields).length > 0
    )
  )
})

const canRetryOcr = computed(() => {
  return !!expenseStore.currentExpense.attachment_receipt
})

const ocrSummaryTitle = computed(() => {
  if (
    expenseStore.ocrPreview.status === 'failed' ||
    expenseStore.ocrPreview.status === 'disabled'
  ) {
    return t('expenses.ocr.failed_title')
  }

  if (
    expenseStore.ocrPreview.status === 'partial' ||
    expenseStore.ocrPreview.status === 'needs_review'
  ) {
    return t('expenses.ocr.review_title')
  }

  if (expenseStore.ocrPreview.status === 'success') {
    return t('expenses.ocr.success_title')
  }

  return t('expenses.ocr.review_title')
})

const ocrSummaryLines = computed(() => {
  const lines = []
  const mappedLabels = Object.keys(expenseStore.ocrPreview.mappedFields).map(
    (field) => getExpenseFieldLabel(field)
  )
  const flaggedLabels = Object.keys(expenseStore.ocrPreview.flaggedFields).map(
    (field) => getExpenseFieldLabel(field)
  )
  const unmappedLabels = Object.keys(expenseStore.ocrPreview.unmappedFields).map(
    (field) => formatRawOcrFieldLabel(field)
  )

  if (mappedLabels.length) {
    lines.push(
      t('expenses.ocr.summary_mapped', {
        fields: mappedLabels.join(', '),
      })
    )
  }

  if (flaggedLabels.length) {
    lines.push(
      t('expenses.ocr.summary_flagged', {
        fields: flaggedLabels.join(', '),
      })
    )
  }

  if (unmappedLabels.length) {
    lines.push(
      t('expenses.ocr.summary_unmapped', {
        fields: unmappedLabels.join(', '),
      })
    )
  }

  return lines
})

expenseStore.resetCurrentExpenseData()
customFieldStore.resetCustomFields()

loadData()

async function onFileInputChange(fileName, file) {
  expenseStore.currentExpense.attachment_receipt = file
  isAttachmentReceiptRemoved.value = false

  await runOcrPreview(file)
}

function onFileInputRemove() {
  expenseStore.currentExpense.attachment_receipt = null
  isAttachmentReceiptRemoved.value = true
  expenseStore.resetOcrPreview()
}

function openCategoryModal() {
  modalStore.openModal({
    title: t('settings.expense_category.add_category'),
    componentName: 'CategoryModal',
    size: 'sm',
  })
}

function onCurrencyChange(v) {
  expenseStore.currentExpense.selectedCurrency = globalStore.currencies.find(
    (c) => c.id === v
  )
}

function getExpenseFieldLabel(field) {
  const fieldLabels = {
    expense_date: t('expenses.expense_date'),
    expense_number: t('expenses.expense_number'),
    amount: t('expenses.amount'),
    currency_id: t('expenses.currency'),
  }

  return fieldLabels[field] || field
}

function formatRawOcrFieldLabel(field) {
  return field.replace(/_/g, ' ')
}

function getOcrFlaggedHelpText(field) {
  const flaggedField = expenseStore.ocrPreview.flaggedFields[field]

  if (!flaggedField) {
    return null
  }

  return t('expenses.ocr.field_review_required', {
    confidence: Math.round((flaggedField.confidence || 0) * 100),
  })
}

function mergeOcrMappedFields() {
  const mappedFields = expenseStore.ocrPreview.mappedFields

  if (mappedFields.expense_date) {
    expenseStore.currentExpense.expense_date = mappedFields.expense_date
  }

  if (mappedFields.expense_number) {
    expenseStore.currentExpense.expense_number = mappedFields.expense_number
  }

  if (mappedFields.amount !== undefined) {
    expenseStore.currentExpense.amount = mappedFields.amount
  }

  if (mappedFields.currency_id) {
    expenseStore.currentExpense.currency_id = mappedFields.currency_id
    onCurrencyChange(mappedFields.currency_id)
  }
}

function isSupportedOcrFile(file) {
  const supportedOcrTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
  ]

  return supportedOcrTypes.includes(file.type)
}

async function retryOcrPreview() {
  if (!expenseStore.currentExpense.attachment_receipt) {
    return
  }

  await runOcrPreview(expenseStore.currentExpense.attachment_receipt)
}

async function runOcrPreview(file) {
  expenseStore.resetOcrPreview()

  if (!file || !isOcrEnabled.value) {
    return
  }

  if (!isSupportedOcrFile(file)) {
    notificationStore.showNotification({
      type: 'info',
      message: t('expenses.ocr.supported_file_types'),
    })
    return
  }

  try {
    await expenseStore.previewExpenseOcr({
      file,
      expenseId: isEdit.value ? route.params.id : null,
    })

    mergeOcrMappedFields()
  } catch (err) {
    return
  }
}

async function searchCategory(search) {
  let res = await categoryStore.fetchCategories({ search })
  if(res.data.data.length>0 && categoryStore.editCategory) {
    let categoryFound = res.data.data.find((c) => c.id==categoryStore.editCategory.id)
    if(!categoryFound) {
      let edit_category = Object.assign({}, categoryStore.editCategory)
      res.data.data.unshift(edit_category)
    }
  }
  return res.data.data
}

async function searchCustomer(search) {
  let res = await customerStore.fetchCustomers({ search })
  if(res.data.data.length>0 && customerStore.editCustomer) {
    let customerFound = res.data.data.find((c) => c.id==customerStore.editCustomer.id)
    if(!customerFound) {
      let edit_customer = Object.assign({}, customerStore.editCustomer)
      res.data.data.unshift(edit_customer)
    }
  }
  return res.data.data
}

async function loadData() {
  if (!isEdit.value) {
    expenseStore.currentExpense.currency_id =
      companyStore.selectedCompanyCurrency.id
    expenseStore.currentExpense.selectedCurrency =
      companyStore.selectedCompanyCurrency
  }

  isFetchingInitialData.value = true
  await expenseStore.fetchPaymentModes({ limit: 'all' })

  if (isEdit.value) {
    const expenseData = await expenseStore.fetchExpense(route.params.id)

    expenseStore.currentExpense.currency_id =
      expenseStore.currentExpense.selectedCurrency.id

    if(expenseData.data) {
      if(!categoryStore.editCategory && expenseData.data.data.expense_category) {
        categoryStore.editCategory = expenseData.data.data.expense_category
      }

      if(!customerStore.editCustomer && expenseData.data.data.customer) {
        customerStore.editCustomer = expenseData.data.data.customer
      }
    }

  } else if (route.query.customer) {
    expenseStore.currentExpense.customer_id = route.query.customer
  }

  isFetchingInitialData.value = false
}

async function submitForm() {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  if (expenseStore.ocrPreview.isRunning) {
    return
  }

  isSaving.value = true

  let formData = {
    ...expenseStore.currentExpense,
    expense_number: expenseStore.currentExpense.expense_number || ''
  }

  try {
    if (isEdit.value) {
      await expenseStore.updateExpense({
        id: route.params.id,
        data: formData,
        isAttachmentReceiptRemoved: isAttachmentReceiptRemoved.value
      })
    } else {
      await expenseStore.addExpense(formData)
    }
    isSaving.value = false
    expenseStore.currentExpense.attachment_receipt = null
    isAttachmentReceiptRemoved.value = false
    router.push('/admin/expenses')
  } catch (err) {
    isSaving.value = false
    return
  }
}

onBeforeUnmount(() => {
  expenseStore.resetCurrentExpenseData()
  customerStore.editCustomer = null
  categoryStore.editCategory = null
})
</script>
