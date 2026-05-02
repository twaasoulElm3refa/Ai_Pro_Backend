import { createI18n } from 'vue-i18n'

import en from './lang/en.json'
import ar from './lang/ar.json'
import ru from './lang/ru.json'

const savedLocale = localStorage.getItem('locale') || 'ar'

document.documentElement.setAttribute('lang', savedLocale)
document.documentElement.setAttribute('dir', savedLocale === 'ar' ? 'rtl' : 'ltr')

const i18n = createI18n({
    legacy: false,
    globalInjection: true,
    locale: savedLocale,
    fallbackLocale: 'en',
    messages: {
        en,
        ar,
        ru
    }
})

export default i18n
