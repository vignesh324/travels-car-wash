import { useState, useEffect } from 'react';
import { expenseCategoriesApi } from '../api/phase2';
import type { ExpenseCategory } from '../types';

export function useExpenseCategories(type?: string) {
    const [categories, setCategories] = useState<ExpenseCategory[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        expenseCategoriesApi.list(type)
            .then(({ data }) => setCategories(data))
            .catch(() => setCategories([]))
            .finally(() => setLoading(false));
    }, [type]);

    return { categories, loading };
}
