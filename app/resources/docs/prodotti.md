# Prodotti

## Come aggiungere un nuovo prodotto

1. Vai su **Prodotti → Nuovo prodotto**
2. Seleziona il **Tipo prodotto** in cima (obbligatorio):
   - **Campionato** — configurazione specifica con codice fornitore definito (es. divano angolare con moduli scelti)
   - **A listino** — prodotto finito a prezzo fisso (es. sedia, tavolo, complemento)
3. Compila **Nome prodotto** e **Campionatura / Descrizione**
4. Nella sidebar destra: scegli Fornitore e Categoria (puoi crearli inline se non esistono ancora)
5. Inserisci prezzi (costo acquisto è privato — non visibile al cliente)
6. Salva

## Campi principali

| Campo | Note |
|---|---|
| **Tipo prodotto** | Obbligatorio. Determina come viene trattato nel sistema |
| **Nome prodotto** | Es. "Katrina – 3Posti estraibile + CL" |
| **Campionatura** | Composizione tecnica, moduli, categoria rivestimento |
| **Codice fornitore** | Copiare verbatim dal catalogo (es. "323/327 + 542/541") |
| **Codice interno** | Generato automaticamente (P0001, P0002...) — non modificabile |

## Galleria immagini

Carica fino a 5 immagini (JPG, PNG, WebP, max 5MB l'una).
La **prima immagine** diventa il thumbnail nella lista prodotti.
Puoi riordinare le immagini trascinandole.

## Prezzi

- **Costo acquisto** = prezzo netto fornitore (PRIVATO — visibile solo allo staff)
- **Prezzo listino** = prezzo di vendita suggerito
- **Markup override** = se vuoto, usa il markup del fornitore (default 1.35)
- **Prezzo cliente** = calcolato automaticamente: costo × markup

## Attributi prodotto

Il campo **Attributi** (KeyValue) raccoglie tutte le caratteristiche del prodotto:
- Rivestimento, Colore, Gambe, Struttura, Materiale top, ecc.
- Aggiungi una riga per ogni attributo rilevante
