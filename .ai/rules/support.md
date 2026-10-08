---
paths:
  - 'app/Support/**'
---

# Support

## Support: helper statici senza stato
Una classe di `app/Support` espone solo metodi `public static` e non ha stato. Ciò che richiede dipendenze iniettate o stato d'istanza va in `app/Services`.
