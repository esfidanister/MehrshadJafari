import {defineRouting} from 'next-intl/routing';

export const routing = defineRouting({
  locales: ['fa', 'en', 'ar', 'zh'],
  defaultLocale: 'fa',
  localePrefix: 'always'
});
