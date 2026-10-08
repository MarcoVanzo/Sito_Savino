---
paths:
  - 'routes/pubbliche/**'
---

# Pubbliche

## File delle rotte pubbliche per lingua
I file di `routes/pubbliche/` restituiscono una closure che `web.php` chiama una volta per lingua: registra le rotte lì dentro e prefissa con `$namePrefix` ogni nome di rotta a cui rimandi.
