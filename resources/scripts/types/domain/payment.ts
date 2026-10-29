import type { Customer } from './customer'
import type { Invoice } from './invoice'
import type { Company } from './company'
import type { Currency } from './currency'
import type { User } from './user'
import type { CustomFieldValue } from './custom-field'

export interface PaymentMethod {
  id: number
  name: string
  company_id: number
  type: string | null
  company?: Company
}

export interface Transaction {
  id: number
  transaction_id: string
  type: string
  status: string
  transaction_date: string
  invoice_id: number | null
  invoice?: Invoice
  company?: Company
}

export interface Payment {
  id: number
  payment_number: string
  payment_date: string
  notes: string | null
  amount: number
  unique_hash: string
  company_id: number
  payment_method_id: number | null
  creator_id: number
  customer_id: number
  exchange_rate: number
  base_amount: number
  currency_id: number
  transaction_id: number | null
  sequence_number: number
  formatted_created_at: string
  formatted_payment_date: string
  payment_pdf_url: string
  customer?: Customer
  allocations?: PaymentAllocation[]
  allocated_amount?: number
  unallocated_amount?: number
  base_allocated_amount?: number
  base_unallocated_amount?: number
  payment_method?: PaymentMethod
  fields?: CustomFieldValue[]
  company?: Company
  currency?: Currency
  transaction?: Transaction
}

export interface PaymentAllocation {
  id?: number
  invoice_id: number
  amount: number
  base_amount?: number
  invoice?: Invoice
}

export interface CreatePaymentPayload {
  payment_date: string
  payment_number: string
  customer_id: number
  amount: number
  allocations?: Array<Pick<PaymentAllocation, 'invoice_id' | 'amount'>>
  payment_method_id?: number | null
  notes?: string | null
  exchange_rate?: number
  customFields?: CustomFieldValue[]
  fields?: CustomFieldValue[]
}
