const messages: Record<string, Record<string, string>> = {
  pt: {
    'app.title': 'ERP SaaS',
    'login.email': 'Email',
    'login.password': 'Senha',
    'login.submit': 'Entrar',
    'login.error.default': 'Erro inesperado',
    'clientes.title': 'Clientes',
    'action.new': 'Novo',
  },
  en: {
    'app.title': 'ERP SaaS',
    'login.email': 'Email',
    'login.password': 'Password',
    'login.submit': 'Sign in',
    'login.error.default': 'Unexpected error',
    'clientes.title': 'Clients',
    'action.new': 'New',
  },
}

// Default language: pt (Português)
let lang = 'pt'

export function setLang(l: string) {
  lang = l
}

export function t(key: string, fallback?: string) {
  return messages[lang]?.[key] ?? fallback ?? key
}

export default { t, setLang }
