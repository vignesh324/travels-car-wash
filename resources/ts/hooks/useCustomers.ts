import { useState, useEffect } from 'react';
import { customersApi } from '../api/resources';
import type { Customer } from '../types';

export function useCustomers() {
    const [customers, setCustomers] = useState<Customer[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        customersApi.list({ is_active: 1 })
            .then(({ data }) => setCustomers(data))
            .catch(() => setCustomers([]))
            .finally(() => setLoading(false));
    }, []);

    return { customers, loading };
}
