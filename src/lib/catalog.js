// Единый источник правды для меню и корзины: из menu.json строим позиции со стабильными ключами.
// Ключ позиции: "<раздел>.<блок>.<номер>", для вариантов (объём/вкус/размер) — с суффиксом "#<номер варианта>".
// Не меняйте порядок позиций внутри блока без необходимости: ключи привязаны к порядку.
const lowerFirst = (s) => s.charAt(0).toLowerCase() + s.slice(1);

export function buildCatalog(menu) {
  return menu.map((cat) => ({
    id: cat.id,
    part: cat.part,
    title: cat.title,
    sections: cat.sections.map((sec, si) => ({
      title: sec.title ?? '',
      items: (sec.items ?? []).map((it, ii) => {
        const id = `${cat.id}.${si}.${ii}`;
        const raw = it.prices ?? [{ vol: it.vol ?? '', price: it.price }];
        const multi = raw.length > 1;
        return {
          id,
          name: it.name,
          fullName: sec.prefix ? `${sec.prefix} ${lowerFirst(it.name)}` : it.name,
          desc: it.desc,
          note: it.note,
          isNew: !!it.new,
          hit: !!it.hit,
          photo: it.photo, // необязательно: "/img/dishes/plov.webp" — фото блюда для карточек на главной
          meta: it.prices ? it.vol : undefined,
          variants: raw.map((v, vi) => ({ key: multi ? `${id}#${vi}` : id, label: v.vol ?? '', price: v.price })),
        };
      }),
    })),
  }));
}

// Плоский справочник «ключ -> название и цена»: его читает и корзина в браузере, и PHP на сервере.
export function priceIndex(catalog) {
  const out = {};
  for (const c of catalog) for (const s of c.sections) for (const it of s.items) for (const v of it.variants) {
    out[v.key] = { name: v.label ? `${it.fullName} · ${v.label}` : it.fullName, price: v.price };
  }
  return out;
}
