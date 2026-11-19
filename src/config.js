// Content for the current edition of the Master Mind series.
// Change this file (and the photo in public/img) for the next edition.
export const API_URL = (import.meta.env.VITE_API_URL || 'https://class.macadz.com').replace(/\/$/, '');

export const EVENT = {
  series: 'Master Mind',
  tagline: 'Meet the Expert',
  title: 'Favour Your Brand',
  speaker: 'Favour Francis',
  speakerRole: 'Creative Director & Brand Consultant',
  speakerPhoto: '/img/favour-francis.jpg',
  startsAt: '2026-11-26T10:30:00+05:30',
  dateLabel: 'Thursday, 26 November 2026',
  dayShort: '26 Nov',
  yearLabel: 'Thursday, 2026',
  timeLabel: '10:30 AM',
  venue: 'MAC Media School',
  venueMap: 'https://share.google/HKLeRtwW1w3lzlQrD',
  fee: 299,
  phone: '+91 90724 83502',
  whatsapp: '919072483502',
  website: 'https://school.macadz.com',
};

export const LEARN = [
  ['What a brand really is', 'Beyond the logo — positioning, promise and the feeling people carry away.'],
  ['Secrets behind big brands', 'Real campaigns taken apart to see the decisions that made them work.'],
  ['Identity & visual language', 'Name, logo, colour, type and tone working together as one system.'],
  ['Brand storytelling', 'Turning a product into a story people remember and repeat.'],
  ['Building your own brand', 'A practical framework you can apply to a business, a client or yourself.'],
  ['Q&A with the expert', `Bring your brand questions — get direct answers from ${'Favour Francis'}.`],
];

export const PROFESSIONS = ['Student', 'Designer', 'Marketer', 'Entrepreneur', 'Business Owner', 'Working Professional', 'Other'];

export const waLink = (text) => `https://wa.me/${EVENT.whatsapp}?text=${encodeURIComponent(text)}`;
export const telLink = `tel:${EVENT.phone.replace(/\s+/g, '')}`;
