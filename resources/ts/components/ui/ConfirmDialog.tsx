import { Button } from './Button';

interface ConfirmDialogProps {
    open: boolean;
    title: string;
    message: string;
    confirmLabel?: string;
    onConfirm: () => void;
    onCancel: () => void;
    loading?: boolean;
    danger?: boolean;
}

export function ConfirmDialog({ open, title, message, confirmLabel = 'Confirm', onConfirm, onCancel, loading, danger = false }: ConfirmDialogProps) {
    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex min-h-full items-center justify-center p-4">
                <div className="fixed inset-0 bg-black/40" onClick={onCancel} />
                <div className="relative bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                    <h3 className="text-base font-semibold text-gray-900 mb-2">{title}</h3>
                    <p className="text-sm text-gray-600 mb-6">{message}</p>
                    <div className="flex gap-3 justify-end">
                        <Button variant="secondary" onClick={onCancel} disabled={loading}>Cancel</Button>
                        <Button variant={danger ? 'danger' : 'primary'} onClick={onConfirm} loading={loading}>
                            {confirmLabel}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
