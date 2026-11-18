import { useEffect, useState } from 'react';
import { EVENT } from './config.js';
import { useEventStatus } from './hooks.js';
import Nav from './components/Nav.jsx';
import Hero from './components/Hero.jsx';
import Register from './components/Register.jsx';
import { Marquee, Learn, Speaker, Details, Faq, Footer } from './components/Sections.jsx';

function StickyCta() {
  const [shown, setShown] = useState(false);
  useEffect(() => {
    const on = () => {
      const reg = document.getElementById('register');
      const r = reg?.getBoundingClientRect();
      const inReg = r && r.top < window.innerHeight && r.bottom > 0;
      setShown(window.scrollY > window.innerHeight * 0.7 && !inReg);
    };
    on();
    window.addEventListener('scroll', on, { passive: true });
    return () => window.removeEventListener('scroll', on);
  }, []);
  return <a href="#register" className={`sticky-cta${shown ? ' is-shown' : ''}`}>Book your seat · ₹{EVENT.fee}</a>;
}

export default function App() {
  const status = useEventStatus();
  return (
    <>
      <Nav />
      <main>
        <Hero status={status} />
        <Marquee />
        <Learn />
        <Speaker />
        <Details />
        <Register status={status} />
        <Faq />
      </main>
      <Footer />
      <StickyCta />
    </>
  );
}
