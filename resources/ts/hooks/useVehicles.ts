import { useState, useEffect } from 'react';
import { vehiclesApi } from '../api/resources';
import type { Vehicle } from '../types';

export function useVehicles() {
    const [vehicles, setVehicles] = useState<Vehicle[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        vehiclesApi.list({ is_active: 1 })
            .then(({ data }) => setVehicles(data))
            .catch(() => setVehicles([]))
            .finally(() => setLoading(false));
    }, []);

    return { vehicles, loading };
}
