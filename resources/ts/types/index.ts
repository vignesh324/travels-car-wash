export interface User {
    id: number;
    uuid: string;
    name: string;
    email: string;
}

export interface BusinessProfile {
    id: number;
    uuid: string;
    business_name: string;
    logo: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    gstin: string | null;
    state: string | null;
    state_code: string | null;
    currency: string;
    timezone: string;
    date_format: string;
    bank_details: Record<string, string> | null;
    upi_id: string | null;
    invoice_prefix: string;
    receipt_prefix: string;
    financial_year_start: string;
    invoice_number_reset: boolean;
    invoice_number_next: number;
    default_payment_terms: string | null;
    invoice_footer: string | null;
    tax_config: Record<string, unknown> | null;
}

export interface Vehicle {
    id: number;
    uuid: string;
    registration_number: string;
    name: string;
    model: string | null;
    type: string | null;
    manufacturer: string | null;
    fuel_type: string;
    seating_capacity: number | null;
    ownership_type: string;
    purchase_date: string | null;
    default_driver_name: string | null;
    default_driver_phone: string | null;
    insurance_expiry: string | null;
    permit_expiry: string | null;
    fitness_expiry: string | null;
    puc_expiry: string | null;
    current_odometer: number | null;
    is_active: boolean;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface Customer {
    id: number;
    uuid: string;
    type: 'individual' | 'corporate' | 'walkin';
    name: string;
    company_name: string | null;
    contact_person: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    billing_address: string | null;
    gstin: string | null;
    state: string | null;
    state_code: string | null;
    payment_terms: string | null;
    credit_limit: string | null;
    notes: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface ApiError {
    message: string;
    errors?: Record<string, string[]>;
}

export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface ExpenseCategory {
    id: number;
    uuid: string;
    name: string;
    type: 'vehicle' | 'general' | 'carwash';
    description: string | null;
    is_capital: boolean;
    is_active: boolean;
    sort_order: number;
}

export interface Trip {
    id: number;
    uuid: string;
    trip_number: string;
    trip_date: string;
    vehicle_id: number;
    customer_id: number | null;
    trip_type: string;
    pickup_location: string | null;
    drop_location: string | null;
    description: string | null;
    start_odometer: number | null;
    end_odometer: number | null;
    distance_km: number | null;
    driver_name: string | null;
    fare_amount: string;
    additional_charges: string;
    discount: string;
    is_taxable: boolean;
    tax_rate: string;
    taxable_amount: string;
    tax_amount: string;
    total_amount: string;
    payment_mode: string | null;
    amount_received: string;
    balance_due: string;
    status: 'draft' | 'completed' | 'cancelled';
    payment_status: 'unpaid' | 'partial' | 'paid';
    is_invoiced: boolean;
    notes: string | null;
    vehicle?: Vehicle;
    customer?: Customer;
    created_at: string;
    updated_at: string;
}

export interface VehicleExpense {
    id: number;
    uuid: string;
    expense_date: string;
    vehicle_id: number;
    category_id: number;
    description: string | null;
    vendor_name: string | null;
    reference_number: string | null;
    amount_before_tax: string;
    gst_amount: string;
    total_amount: string;
    payment_mode: string;
    payment_status: 'paid' | 'unpaid' | 'partial';
    payment_date: string | null;
    notes: string | null;
    vehicle?: Vehicle;
    category?: ExpenseCategory;
    created_at: string;
    updated_at: string;
}

export interface BusinessExpense {
    id: number;
    uuid: string;
    expense_date: string;
    category_id: number;
    source: 'general' | 'carwash';
    description: string | null;
    vendor_name: string | null;
    total_amount: string;
    payment_mode: string;
    payment_status: 'paid' | 'unpaid' | 'partial';
    notes: string | null;
    category?: ExpenseCategory;
    created_at: string;
    updated_at: string;
}

export interface CarWashService {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    default_price: string;
    tax_rate: string;
    is_active: boolean;
    sort_order: number;
}

export interface CarWashTransactionItem {
    id: number;
    transaction_id: number;
    service_id: number | null;
    service_name: string;
    quantity: string;
    unit_price: string;
    discount: string;
    taxable_amount: string;
    tax_rate: string;
    tax_amount: string;
    total_amount: string;
}

export interface CarWashTransaction {
    id: number;
    uuid: string;
    transaction_number: string;
    service_datetime: string;
    customer_id: number | null;
    vehicle_registration: string | null;
    subtotal: string;
    discount: string;
    taxable_amount: string;
    tax_amount: string;
    total_amount: string;
    amount_paid: string;
    balance_due: string;
    payment_mode: string | null;
    payment_status: 'unpaid' | 'partial' | 'paid';
    invoice_id: number | null;
    notes: string | null;
    customer?: Customer;
    items?: CarWashTransactionItem[];
    created_at: string;
    updated_at: string;
}

export interface InvoiceItem {
    id: number;
    uuid: string;
    invoice_id: number;
    description: string;
    source: 'travels' | 'carwash';
    quantity: string;
    unit_price: string;
    discount: string;
    taxable_amount: string;
    tax_rate: string;
    cgst_rate: string;
    sgst_rate: string;
    igst_rate: string;
    tax_amount: string;
    total_amount: string;
    sort_order: number;
}

export interface Invoice {
    id: number;
    uuid: string;
    invoice_number: string;
    invoice_date: string;
    due_date: string | null;
    customer_id: number;
    source: 'travels' | 'carwash' | 'mixed';
    customer_name: string;
    customer_gstin: string | null;
    customer_address: string | null;
    customer_state: string | null;
    customer_state_code: string | null;
    business_name: string | null;
    business_gstin: string | null;
    place_of_supply: string | null;
    subtotal: string;
    discount_total: string;
    taxable_amount: string;
    cgst_amount: string;
    sgst_amount: string;
    igst_amount: string;
    tax_total: string;
    total_amount: string;
    amount_paid: string;
    balance_due: string;
    payment_terms: string | null;
    notes: string | null;
    status: 'draft' | 'issued' | 'partial' | 'paid' | 'cancelled';
    cancellation_reason: string | null;
    issued_at: string | null;
    customer?: Customer;
    items?: InvoiceItem[];
    created_at: string;
    updated_at: string;
}

export interface PaymentAllocation {
    id: number;
    uuid: string;
    payment_id: number;
    invoice_id: number;
    amount: string;
    notes: string | null;
    invoice?: Invoice;
}

export interface Payment {
    id: number;
    uuid: string;
    payment_number: string;
    customer_id: number;
    payment_date: string;
    amount: string;
    allocated_amount: string;
    unallocated_amount: string;
    payment_method: string;
    transaction_reference: string | null;
    notes: string | null;
    status: 'active' | 'reversed' | 'refunded';
    reversal_reason: string | null;
    customer?: Customer;
    allocations?: PaymentAllocation[];
    created_at: string;
    updated_at: string;
}

export interface DashboardSummary {
    today: {
        trip_count: number;
        trip_revenue: number;
        collected: number;
        expenses: number;
    };
    month: {
        revenue: number;
        collected: number;
        expenses: number;
        estimated_profit: number;
    };
    outstanding_balance: number;
    expiry_alerts: number;
}
