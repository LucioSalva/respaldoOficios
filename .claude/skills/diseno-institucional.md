# Skill: diseño-institucional

## Propósito
Aplicar el sistema de diseño visual institucional de la Tesorería Municipal - STIyC
a las vistas PHP + Bootstrap de la app "Respaldo de Oficios".

Siempre que el usuario pida cambiar apariencia, mejorar UI, actualizar colores o
rediseñar componentes en este proyecto, usa este sistema como referencia autoritativa.

---

## Colores Institucionales Oficiales

| Nombre     | HEX       | RGB              | Pantone  | Uso principal                        |
|------------|-----------|------------------|----------|--------------------------------------|
| Guinda     | `#691C32` | 105, 28, 50      | 7421 C   | Color primario, navbar, botones CTA  |
| Dorado     | `#BC955C` | 188, 149, 92     | 465 C    | Acentos, bordes, botones secundarios |
| Crema      | `#ECD798` | 236, 215, 152    | 7402 C   | Fondos suaves, highlights, badges    |

### Derivados para uso en UI

```
--guinda:          #691C32   /* primario */
--guinda-oscuro:   #4e1424   /* hover primario */
--guinda-claro:    #8a2642   /* gradiente inicio */
--guinda-suave:    #f5e8ec   /* fondo tenue guinda */
--dorado:          #BC955C   /* secundario */
--dorado-oscuro:   #8a6a30   /* hover secundario */
--dorado-claro:    #d4a86a   /* gradiente inicio */
--dorado-suave:    #fdf6e8   /* fondo tenue dorado */
--crema:           #ECD798   /* acento / highlight */
--crema-suave:     #fdf9ee   /* fondo muy claro */
--texto-oscuro:    #1a0a0f   /* texto sobre fondos claros */
--texto-guinda:    #691C32   /* texto sobre crema/beige */
```

---

## Sistema de Componentes

### 1. BOTONES

Regla general: el `border-bottom` más grueso da efecto 3D pulsable.
Al hacer `:active` se reduce ese borde para simular presión.

#### Botón Primario (Guinda)
```html
<button class="btn-institucional">
  <i class="fa-solid fa-check"></i> Guardar
</button>
```
```css
.btn-institucional {
  font-size: 1rem;
  font-weight: 700;
  padding: 10px 25px;
  border-radius: 0.7rem;
  background-image: linear-gradient(#8a2642, #691C32);
  border: 2px solid #4e1424;
  border-bottom: 5px solid #4e1424;
  box-shadow: 0px 1px 6px 0px rgba(105, 28, 50, 0.45);
  color: #fff;
  cursor: pointer;
  transition: 0.2s linear;
  transform: translate(0, -3px);
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.btn-institucional:hover {
  background-image: linear-gradient(#9e2e4c, #7d2239);
  transform: translate(0, -4px);
  box-shadow: 0px 3px 10px 0px rgba(105, 28, 50, 0.5);
}
.btn-institucional:active {
  transform: translate(0, 0);
  border-bottom: 2px solid #4e1424;
  box-shadow: none;
}
```

#### Botón Secundario (Dorado)
```html
<button class="btn-dorado">
  <i class="fa-solid fa-pen-to-square"></i> Editar
</button>
```
```css
.btn-dorado {
  font-size: 1rem;
  font-weight: 700;
  padding: 10px 25px;
  border-radius: 0.7rem;
  background-image: linear-gradient(#d4a86a, #BC955C);
  border: 2px solid #8a6a30;
  border-bottom: 5px solid #8a6a30;
  box-shadow: 0px 1px 6px 0px rgba(188, 149, 92, 0.45);
  color: #1a0a0f;
  cursor: pointer;
  transition: 0.2s linear;
  transform: translate(0, -3px);
}
.btn-dorado:hover { transform: translate(0, -4px); }
.btn-dorado:active {
  transform: translate(0, 0);
  border-bottom: 2px solid #8a6a30;
}
```

#### Botón Outline (Guinda)
```css
.btn-outline-institucional {
  background: transparent;
  border: 2px solid #691C32;
  border-bottom: 4px solid #691C32;
  color: #691C32;
  font-weight: 700;
  border-radius: 0.7rem;
  padding: 9px 22px;
  transition: 0.2s linear;
  transform: translate(0, -2px);
}
.btn-outline-institucional:hover {
  background: #691C32;
  color: #fff;
}
.btn-outline-institucional:active {
  transform: translate(0, 0);
  border-bottom: 2px solid #691C32;
}
```

---

### 2. INPUTS / CAMPOS DE TEXTO

```html
<input placeholder="Escribe aquí..." class="input-institucional" type="text">
<textarea class="input-institucional"></textarea>
<select class="input-institucional"></select>
```
```css
.input-institucional,
.form-control,
.form-select {
  padding: 10px 14px;
  border: 2px solid #d4bfa0;
  border-radius: 0.6rem;
  font-size: 1.05rem;
  color: #1a0a0f;
  outline: none;
  background: #fff;
  transition: border-color 0.2s, box-shadow 0.2s;
  width: 100%;
}
.input-institucional:focus,
.form-control:focus,
.form-select:focus {
  border-color: #691C32;
  box-shadow: 0 0 0 0.2rem rgba(105, 28, 50, 0.18);
}
```

---

### 3. CARDS

#### Card Estándar (con barra de título guinda)
```html
<div class="card-institucional">
  <div class="card-institucional__header">
    <span class="card-dot red"></span>
    <span class="card-dot yellow"></span>
    <span class="card-dot green"></span>
    <span class="card-institucional__title">Título</span>
  </div>
  <div class="card-institucional__body">
    <!-- contenido -->
  </div>
</div>
```
```css
.card-institucional {
  background: #fff;
  border-radius: 10px;
  border: 1px solid rgba(105,28,50,0.12);
  box-shadow: 0 2px 12px rgba(105,28,50,0.08);
  overflow: hidden;
}
.card-institucional__header {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 14px;
  background: linear-gradient(135deg, #8a2642 0%, #691C32 100%);
  border-bottom: 3px solid #BC955C;
}
.card-dot {
  width: 11px; height: 11px;
  border-radius: 50%;
  display: inline-block;
}
.card-dot.red    { background: #ff605c; }
.card-dot.yellow { background: #ffbd44; }
.card-dot.green  { background: #00ca4e; }
.card-institucional__title {
  font-size: 0.9rem;
  font-weight: 700;
  color: #ECD798;
  margin-left: 6px;
}
.card-institucional__body { padding: 1.25rem; }
```

---

### 4. NOTIFICACIONES / ALERTAS

```html
<div class="notif-institucional notif-success">
  <div class="notif-wave-bg"></div>
  <div class="notif-icon">
    <i class="fa-solid fa-circle-check"></i>
  </div>
  <div class="notif-text">
    <p class="notif-title">Operación exitosa</p>
    <p class="notif-sub">El oficio fue registrado correctamente</p>
  </div>
  <i class="fa-solid fa-xmark notif-close"></i>
</div>
```
```css
.notif-institucional {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 16px;
  border-radius: 10px;
  background: #fff;
  box-shadow: rgba(149,157,165,0.2) 0px 8px 24px;
  position: relative;
  overflow: hidden;
  min-height: 70px;
  border-left: 5px solid #691C32;
}
.notif-success { border-left-color: #28a745; }
.notif-warning { border-left-color: #BC955C; }
.notif-error   { border-left-color: #691C32; }
.notif-info    { border-left-color: #0d6efd; }

.notif-icon {
  width: 38px; height: 38px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
}
.notif-success .notif-icon { background: #d4edda; color: #28a745; }
.notif-warning .notif-icon { background: #fdf6e8; color: #BC955C; }
.notif-error   .notif-icon { background: #f5e8ec; color: #691C32; }

.notif-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #1a0a0f;
}
.notif-sub {
  margin: 0;
  font-size: 0.85rem;
  color: #6c757d;
}
.notif-close {
  margin-left: auto;
  color: #888;
  cursor: pointer;
  font-size: 1rem;
  flex-shrink: 0;
}
.notif-close:hover { color: #691C32; }
```

---

### 5. CHECKBOXES ANIMADOS

```html
<div class="check-wrapper">
  <input style="display:none" id="cb1" type="checkbox">
  <label class="check-institucional" for="cb1">
    <svg viewBox="0 0 18 18" height="36px" width="36px">
      <path d="M1,9 L1,3.5 C1,2 2,1 3.5,1 L14.5,1 C16,1 17,2 17,3.5 L17,14.5 C17,16 16,17 14.5,17 L3.5,17 C2,17 1,16 1,14.5 L1,9 Z"></path>
      <polyline points="1 9 7 14 15 4"></polyline>
    </svg>
  </label>
</div>
```
```css
.check-institucional {
  cursor: pointer;
  position: relative;
  display: inline-block;
  width: 18px; height: 18px;
  -webkit-tap-highlight-color: transparent;
  transform: translate3d(0,0,0);
}
.check-institucional svg {
  fill: none;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke: #c8ccd4;
  stroke-width: 1.5;
  transition: all 0.2s ease;
}
.check-institucional svg path  { stroke-dasharray: 60; stroke-dashoffset: 0; }
.check-institucional svg polyline { stroke-dasharray: 22; stroke-dashoffset: 66; }
.check-institucional:hover svg { stroke: #BC955C; }

input:checked + .check-institucional svg { stroke: #BC955C; }
input:checked + .check-institucional svg path {
  stroke-dashoffset: 60;
  transition: all 0.3s linear;
}
input:checked + .check-institucional svg polyline {
  stroke-dashoffset: 42;
  stroke: #691C32;
  transition: all 0.2s linear;
  transition-delay: 0.15s;
  animation: check-bounce 0.6s ease;
}
@keyframes check-bounce {
  from { transform: scale(1,1); }
  30%  { transform: scale(1.25,0.75); }
  40%  { transform: scale(0.75,1.25); }
  50%  { transform: scale(1.15,0.85); }
  65%  { transform: scale(0.95,1.05); }
  75%  { transform: scale(1.05,0.95); }
  to   { transform: scale(1,1); }
}
```

---

## Variables CSS Completas (pegar en :root)

```css
:root {
  --guinda:         #691C32;
  --guinda-oscuro:  #4e1424;
  --guinda-claro:   #8a2642;
  --guinda-suave:   #f5e8ec;
  --dorado:         #BC955C;
  --dorado-oscuro:  #8a6a30;
  --dorado-claro:   #d4a86a;
  --dorado-suave:   #fdf6e8;
  --crema:          #ECD798;
  --crema-suave:    #fdf9ee;
  --texto-oscuro:   #1a0a0f;
  --texto-muted:    #6b5a5e;
  --borde:          #d4bfa0;
  --fondo:          #faf7f2;
  --fondo-card:     #ffffff;
  --radius:         10px;
  --radius-lg:      14px;
  --shadow-sm:      0 2px 8px rgba(105,28,50,.08);
  --shadow-md:      0 4px 18px rgba(105,28,50,.15);
  --font-base:      1.1rem;
}
```

---

## Reglas de Aplicación

1. **Color primario = Guinda `#691C32`** — navbar, botones CTA, headers de card, títulos importantes
2. **Color secundario = Dorado `#BC955C`** — acentos, bordes activos, botones de acción secundaria
3. **Color de fondo suave = Crema `#ECD798`** — badges, highlights, fondos de secciones
4. **Degradados siempre de claro a oscuro** (arriba claro, abajo oscuro del mismo color)
5. **Botones con border-bottom grueso** = efecto 3D pulsable institucional
6. **Focus en inputs = borde guinda** con sombra rgba(105,28,50,0.18)
7. **Cards con header guinda** y franja dorada inferior en el header
8. **Navbar = degradado guinda** con borde dorado inferior
9. **NO usar azul** como color primario — toda referencia a blue/primary se reemplaza con guinda
10. **Personas mayores**: botones grandes (min 44px alto), fuente mínima 1rem, labels en negrita

---

## Cómo usar esta skill

Cuando se invoque `/diseno-institucional` o se pida "aplica el diseño institucional":
1. Leer los archivos CSS y vistas afectados
2. Reemplazar colores azules/morados por la paleta guinda-dorado-crema
3. Aplicar el estilo de botones con border-bottom 3D
4. Aplicar inputs con borde guinda al focus
5. Aplicar cards con header guinda + borde dorado
6. Verificar contraste para accesibilidad (texto blanco sobre guinda ✓)
7. Mantener Bootstrap como base, solo sobreescribir con clases propias
