import { useState } from 'react';
import api from '../api/client';
import toast from 'react-hot-toast';

export function usePdfDownload() {
    const [downloading, setDownloading] = useState(false);

    const download = async (pdfPath: string, filename: string) => {
        setDownloading(true);
        try {
            const { data } = await api.get(pdfPath, { responseType: 'blob' });
            const url = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }));
            const tab = window.open(url, '_blank');
            // Revoke after the tab has loaded the blob
            if (tab) tab.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
        } catch {
            toast.error('Failed to generate PDF');
        } finally {
            setDownloading(false);
        }
    };

    return { download, downloading };
}
