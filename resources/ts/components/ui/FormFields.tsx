import type { InputHTMLAttributes, SelectHTMLAttributes, TextareaHTMLAttributes, ReactNode } from 'react';

interface FieldProps {
    label?: string;
    error?: string;
    required?: boolean;
    hint?: string;
}

interface InputProps extends FieldProps, InputHTMLAttributes<HTMLInputElement> {}
interface SelectProps extends FieldProps, SelectHTMLAttributes<HTMLSelectElement> { children: ReactNode; }
interface TextareaProps extends FieldProps, TextareaHTMLAttributes<HTMLTextAreaElement> {}

const base = 'block w-full rounded-lg border px-3 py-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-0 disabled:bg-gray-50 disabled:text-gray-500';
const normal = 'border-gray-300 focus:border-blue-500 focus:ring-blue-500/20';
const errored = 'border-red-300 focus:border-red-500 focus:ring-red-500/20 bg-red-50/30';

export function Input({ label, error, required, hint, className = '', ...props }: InputProps) {
    return (
        <div>
            {label && (
                <label className="block text-xs font-medium text-gray-600 mb-1">
                    {label}{required && <span className="text-red-500 ml-0.5">*</span>}
                </label>
            )}
            <input {...props} className={`${base} ${error ? errored : normal} ${className}`} />
            {hint && !error && <p className="mt-1 text-xs text-gray-400">{hint}</p>}
            {error && <p className="mt-1 text-xs text-red-600 flex items-center gap-1">
                <svg className="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd"/></svg>
                {error}
            </p>}
        </div>
    );
}

export function Select({ label, error, required, hint, children, className = '', ...props }: SelectProps) {
    return (
        <div>
            {label && (
                <label className="block text-xs font-medium text-gray-600 mb-1">
                    {label}{required && <span className="text-red-500 ml-0.5">*</span>}
                </label>
            )}
            <select {...props} className={`${base} ${error ? errored : normal} ${className}`}>
                {children}
            </select>
            {hint && !error && <p className="mt-1 text-xs text-gray-400">{hint}</p>}
            {error && <p className="mt-1 text-xs text-red-600 flex items-center gap-1">
                <svg className="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd"/></svg>
                {error}
            </p>}
        </div>
    );
}

export function Textarea({ label, error, required, hint, className = '', ...props }: TextareaProps) {
    return (
        <div>
            {label && (
                <label className="block text-xs font-medium text-gray-600 mb-1">
                    {label}{required && <span className="text-red-500 ml-0.5">*</span>}
                </label>
            )}
            <textarea {...props} rows={props.rows ?? 3} className={`${base} ${error ? errored : normal} ${className}`} />
            {hint && !error && <p className="mt-1 text-xs text-gray-400">{hint}</p>}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

// Inline filter input (no label wrapper)
export const filterInputClass = 'rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-colors';
