---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## Messaggi di validazione da messages() e chiavi lang
Nei Form Request i messaggi stanno in `messages()` e restituiscono chiavi `__('messages.validation.…')` di `lang/*/messages.php`, mai stringhe scritte né il blocco `custom` di `validation.php`. I nomi leggibili dei campi in `lang/*/validation.php` sotto `attributes`.
