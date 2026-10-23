// @ts-nocheck
// src/pages/api/send.ts

export const prerender = false;

export async function POST({ request }) {
  try {
    const data = await request.json();
    const token = import.meta.env.TELEGRAM_BOT_TOKEN;
    const chatId = import.meta.env.TELEGRAM_CHAT_ID;

    if (!token || !chatId) {
      return new Response(JSON.stringify({ error: "Не настроены переменные окружения" }), { status: 500 });
    }

    let text = `<b>🔔 Новая заявка с сайта!</b>\n\n`;
    
    if (data.type === 'booking') {
      text += `<b>Тип:</b> Бронирование стола\n`;
      text += `<b>Имя:</b> ${data.name}\n`;
      text += `<b>Телефон:</b> ${data.phone}\n`;
      text += `<b>Дата и время:</b> ${data.date} в ${data.time}\n`;
      text += `<b>Гостей:</b> ${data.guests}\n`;
    } else if (data.type === 'order') {
      text += `<b>Тип:</b> Заказ на доставку/самовывоз\n`;
      text += `<b>Имя:</b> ${data.name}\n`;
      text += `<b>Телефон:</b> ${data.phone}\n`;
      text += `<b>Способ:</b> ${data.mode === 'delivery' ? 'Доставка' : 'Самовывоз'}\n`;
      if (data.address) text += `<b>Адрес:</b> ${data.address}\n`;
      
      text += `\n<b>Состав заказа:</b>\n`;
      data.items.forEach((item) => {
        text += `• ${item.name} — ${item.qty} шт. (${item.price * item.qty} ₽)\n`;
      });
      
      text += `\n<b>Сумма блюд:</b> ${data.total} ₽\n`;
      if (data.mode === 'delivery') {
        text += `<b>Стоимость доставки:</b> рассчитать и подтвердить у клиента\n`;
      }
      if (data.comment) text += `<b>Комментарий:</b> ${data.comment}\n`;
    } else if (data.type === 'feedback') {
      text += `<b>Тип:</b> Обратная связь\n`;
      text += `<b>Имя:</b> ${data.name}\n`;
      text += `<b>Телефон:</b> ${data.phone}\n`;
      text += `<b>Сообщение:</b> ${data.message}\n`;
    }

    const tgUrl = `https://api.telegram.org/bot${token}/sendMessage`;
    
    let success = false;
    for (let i = 0; i < 2; i++) {
      try {
        const response = await fetch(tgUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            chat_id: chatId,
            text: text,
            parse_mode: 'HTML'
          })
        });
        
        if (response.ok) {
          success = true;
          break;
        }
      } catch (error) {
        console.error(`Попытка ${i + 1} не удалась:`, error);
        await new Promise((resolve) => setTimeout(resolve, 1000));
      }
    }

    if (!success) {
      return new Response(JSON.stringify({ error: "Не удалось доставить в Telegram" }), { status: 500 });
    }

    return new Response(JSON.stringify({ ok: true }), { status: 200 });
  } catch (error) {
    console.error(error);
    return new Response(JSON.stringify({ error: "Ошибка сервера" }), { status: 500 });
  }
}