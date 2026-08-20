import { chromium } from "playwright";

const url = process.argv[2];

// Таймауты и лимиты (управляются через .env)
const navTimeoutMs = Number(process.env.AUDIT_PLAYWRIGHT_TIMEOUT_MS || 45000);   // таймаут goto
const settleMs = Number(process.env.AUDIT_PLAYWRIGHT_SETTLE_MS || 2000);        // дать странице дорисоваться
const maxHtmlBytes = Number(process.env.AUDIT_MAX_HTML_BYTES || 2500000);

const headlessEnv = (process.env.AUDIT_HEADLESS ?? "1").toString().trim();
const headless = !(headlessEnv === "0" || headlessEnv.toLowerCase() === "false");

// какие ресурсы блокировать для ускорения (рекомендую true)
const blockHeavy = (process.env.AUDIT_BLOCK_HEAVY ?? "1").toString().trim() !== "0";

if (!url) {
  console.error(JSON.stringify({ error: "No URL provided" }));
  process.exit(1);
}

const clampBytes = (str, maxBytes) => {
  const buf = Buffer.from(str, "utf8");
  if (buf.length <= maxBytes) return str;
  return buf.subarray(0, maxBytes).toString("utf8");
};

async function launchChromeOrFallback() {
  // 1) Пытаемся открыть установленный Google Chrome
  try {
    return await chromium.launch({
      channel: "chrome",
      headless,
      args: [
        "--no-sandbox",
        "--disable-setuid-sandbox",
        "--disable-dev-shm-usage",
        "--disable-gpu",
      ],
    });
  } catch (e) {
    // 2) Если Chrome не найден/не доступен — fallback на встроенный Chromium Playwright
    return await chromium.launch({
      headless,
      args: [
        "--no-sandbox",
        "--disable-setuid-sandbox",
        "--disable-dev-shm-usage",
        "--disable-gpu",
      ],
    });
  }
}

(async () => {
  const t0 = Date.now();
  let browser;

  try {
    browser = await launchChromeOrFallback();

    const context = await browser.newContext({
      userAgent:
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122 Safari/537.36",
      viewport: { width: 1365, height: 768 },
      locale: "ru-RU",
    });

    const page = await context.newPage();

    // ускоряем и уменьшаем риск зависаний
    page.setDefaultNavigationTimeout(navTimeoutMs);
    page.setDefaultTimeout(navTimeoutMs);

    let mainStatus = null;

    page.on("response", (resp) => {
      // Иногда page.url() меняется из-за редиректов, поэтому берём статус первого "document"
      try {
        if (resp.request().resourceType() === "document" && mainStatus === null) {
          mainStatus = resp.status();
        }
      } catch (_) {}
    });

    // Блокируем тяжёлые ресурсы (картинки/шрифты/медиа), чтобы не ждать лишнего
    if (blockHeavy) {
      await page.route("**/*", (route) => {
        const type = route.request().resourceType();
        if (type === "image" || type === "media" || type === "font") {
          return route.abort();
        }
        return route.continue();
      });
    }

    // DOMContentLoaded — самый стабильный вариант для “аудитора”
    const response = await page.goto(url, {
      waitUntil: "domcontentloaded",
      timeout: navTimeoutMs,
    });

    const status = response ? response.status() : mainStatus;

    // даём js/реакту дорисовать текст
    if (settleMs > 0) {
      await page.waitForTimeout(settleMs);
    }

    // Стараться “достать” финальный HTML
    const html = await page.content();
    const finalUrl = page.url();

    const out = {
      final_url: finalUrl,
      status: status || null,
      render_ms: Date.now() - t0,
      html: clampBytes(html, maxHtmlBytes),
      engine: "playwright",
      browser: "chrome_or_chromium",
      headless,
      blocked_heavy: blockHeavy,
    };

    console.log(JSON.stringify(out));
    await page.close().catch(() => {});
    await context.close().catch(() => {});
    await browser.close().catch(() => {});
  } catch (e) {
    try {
      if (browser) await browser.close().catch(() => {});
    } catch (_) {}

    console.error(JSON.stringify({ error: String(e) }));
    process.exit(2);
  }
})();
