import menu from '../../data/menu.json';
import delivery from '../../data/delivery.json';
import { buildCatalog, priceIndex } from '../../lib/catalog.js';

// Статический файл /api/menu.json: цены для корзины и для проверки заказа на сервере (PHP).
export function GET() {
  const body = { items: priceIndex(buildCatalog(menu)), delivery };
  return new Response(JSON.stringify(body), { headers: { 'Content-Type': 'application/json; charset=utf-8' } });
}
