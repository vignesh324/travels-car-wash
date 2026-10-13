interface PlaceholderPageProps {
    title: string;
    phase: string;
    description: string;
}

export function PlaceholderPage({ title, phase, description }: PlaceholderPageProps) {
    return (
        <div>
            <h1 className="text-2xl font-bold text-gray-900 mb-2">{title}</h1>
            <div className="bg-amber-50 border border-amber-200 rounded-xl p-6 mt-6">
                <p className="text-sm font-semibold text-amber-800 mb-1">Coming in {phase}</p>
                <p className="text-sm text-amber-700">{description}</p>
            </div>
        </div>
    );
}
