// TODO re-declare functions from upstream library
// import { translate, translatePlural } from '@nextcloud/l10n/dist/translation.d.ts'

declare function t(app: string, text: string, vars?: { [key: string]: string }, number?: number, options?: any): string;
declare function n(app: string, textSingular: string, textPlural: string, number: number, vars?: { [key: string]: string }, options?: any): string;
