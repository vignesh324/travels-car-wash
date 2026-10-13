const raw =
    document.querySelector<HTMLMetaElement>('meta[name="app-base-path"]')?.content.trim() ?? '';

export const appBasePath = raw.replace(/\/+$/, '');

export function appPath(path: string): string {
    return `${appBasePath}/${path.replace(/^\/+/, '')}`;
}
