// info@fdartgallery.com -> birden fazla alici.
// Cloudflare Email Routing tek kuralda tek hedefe izin verdigi icin dagitimi
// bu Worker yapiyor. Hedeflerin Email Routing'de DOGRULANMIS olmasi sart.
const ALICILAR = ["blgnklc@gmail.com", "fusundogan80@gmail.com"];

export default {
  async email(message) {
    for (const adres of ALICILAR) {
      try {
        await message.forward(adres);
        console.log("iletildi:", adres, "| gonderen:", message.from);
      } catch (e) {
        // Bir alici basarisiz olursa digerleri yine de denensin.
        console.log("HATA:", adres, String(e));
      }
    }
  },
};
