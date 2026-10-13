import { useState } from 'react';
import CarWashTransactionsTab from './CarWashTransactionsTab';
import CarWashServicesTab from './CarWashServicesTab';

export default function CarWashPage() {
    const [tab, setTab] = useState<'transactions' | 'services'>('transactions');

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Car Wash</h1>
                <p className="text-sm text-gray-500 mt-0.5">Walk-in billing, transactions and service catalogue</p>
            </div>

            <div className="flex gap-1 bg-gray-100 rounded-lg p-1 mb-6 w-fit">
                <button
                    onClick={() => setTab('transactions')}
                    className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${tab === 'transactions' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'}`}
                >
                    Transactions
                </button>
                <button
                    onClick={() => setTab('services')}
                    className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${tab === 'services' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'}`}
                >
                    Services
                </button>
            </div>

            {tab === 'transactions' ? <CarWashTransactionsTab /> : <CarWashServicesTab />}
        </div>
    );
}
