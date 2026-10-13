import { useState } from 'react';
import VehicleExpensesTab from './VehicleExpensesTab';
import BusinessExpensesTab from './BusinessExpensesTab';

export default function ExpensesPage() {
    const [tab, setTab] = useState<'vehicle' | 'business'>('vehicle');

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Expenses</h1>
                <p className="text-sm text-gray-500 mt-0.5">Vehicle and business expense tracking</p>
            </div>

            <div className="flex gap-1 bg-gray-100 rounded-lg p-1 mb-6 w-fit">
                <button
                    onClick={() => setTab('vehicle')}
                    className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${tab === 'vehicle' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'}`}
                >
                    Vehicle Expenses
                </button>
                <button
                    onClick={() => setTab('business')}
                    className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${tab === 'business' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'}`}
                >
                    Business Expenses
                </button>
            </div>

            {tab === 'vehicle' ? <VehicleExpensesTab /> : <BusinessExpensesTab />}
        </div>
    );
}
