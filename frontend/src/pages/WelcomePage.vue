<script setup lang="ts">
const STEPS = [
  {
    num: '1',
    title: 'Type your schedule',
    body:
      'Describe opening hours in plain English — a single sentence covers what used to take 15 clicks across 7 rows of time pickers.',
  },
  {
    num: '2',
    title: 'Review what HoursAI understood',
    body:
      'See a side-by-side of your current schedule and what HoursAI proposes. Nothing is saved until you approve.',
  },
  {
    num: '3',
    title: 'Apply across venues and channels',
    body:
      'One prompt configures multiple venues, menus, or delivery channels at once. Mistakes are easy to roll back.',
  },
]

const EXAMPLES = [
  "We're open Mon–Fri 8am to 10pm, closed weekends.",
  'Breakfast available every day 7am to 11am.',
  'Delivery Monday to Friday 10am to 10pm, weekends 11am to 9pm.',
  "Closed Christmas Day and New Year's Day.",
]
</script>

<template>
  <div class="welcome">
    <section class="hero">
      <span class="hero__halo" aria-hidden="true"></span>
      <span class="hero__sparkle" aria-hidden="true">✦</span>
      <h1 class="hero__title">HoursAI</h1>
      <p class="hero__tagline">
        Configure store hours, menus, and delivery times in plain English.
      </p>
      <p class="hero__lead">
        Restaurant operators reset their schedules four times a year — for new
        seasons, holidays, openings. Today that means 15+ clicks per venue.
        HoursAI replaces that with a single sentence.
      </p>
    </section>

    <section class="how">
      <h2 class="section-title">How it works</h2>
      <div class="how__grid">
        <article v-for="s in STEPS" :key="s.num" class="step">
          <span class="step__num" aria-hidden="true">{{ s.num }}</span>
          <h3 class="step__title">{{ s.title }}</h3>
          <p class="step__body">{{ s.body }}</p>
        </article>
      </div>
    </section>

    <section class="examples">
      <h2 class="section-title">Things you can type</h2>
      <ul class="examples__list">
        <li v-for="(ex, i) in EXAMPLES" :key="i" class="examples__item">
          <span class="examples__quote" aria-hidden="true">"</span>
          <span class="examples__text">{{ ex }}</span>
        </li>
      </ul>
    </section>

    <section class="cta">
      <span class="cta__arrow" aria-hidden="true">←</span>
      <div class="cta__text">
        <strong>Pick a brand from the sidebar</strong> to start configuring.
      </div>
    </section>
  </div>
</template>

<style scoped>
.welcome {
  display: flex;
  flex-direction: column;
  gap: 48px;
  padding-bottom: 32px;
}

/* Hero */
.hero {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 12px;
  padding: 56px 48px;
  border-radius: var(--radius-lg);
  background: linear-gradient(
    135deg,
    var(--primary-accent-100) 0%,
    var(--primary-accent-80) 100%
  );
  color: var(--white);
  overflow: hidden;
  isolation: isolate;
}

.hero__halo {
  position: absolute;
  top: -120px;
  right: -120px;
  width: 380px;
  height: 380px;
  border-radius: 50%;
  background: radial-gradient(
    circle,
    rgba(255, 255, 255, 0.18) 0%,
    rgba(255, 255, 255, 0) 70%
  );
  z-index: 0;
}

.hero__sparkle {
  position: relative;
  font-size: 28px;
  color: var(--white);
  opacity: 0.9;
  animation: sparkle-pulse 2.6s ease-in-out infinite;
}
@keyframes sparkle-pulse {
  0%, 100% { opacity: 0.6; transform: scale(1); }
  50%      { opacity: 1;   transform: scale(1.15); }
}

.hero__title {
  position: relative;
  font-size: 48px;
  line-height: 1.05;
  letter-spacing: -1px;
  color: var(--white);
  margin: 0;
}

.hero__tagline {
  position: relative;
  font-size: 18px;
  color: rgba(255, 255, 255, 0.92);
  max-width: 560px;
  line-height: 1.5;
}

.hero__lead {
  position: relative;
  font-size: 14px;
  color: rgba(255, 255, 255, 0.75);
  max-width: 640px;
  line-height: 1.6;
  margin-top: 8px;
}

/* Section titles */
.section-title {
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  font-weight: var(--font-weight-semibold);
  color: var(--grayscale-60);
  margin-bottom: 16px;
}

/* How it works */
.how__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}

.step {
  position: relative;
  padding: 24px;
  background: var(--white);
  border: 1px solid var(--transparent-05);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: transform 0.15s, box-shadow 0.15s;
}
.step:hover {
  transform: translateY(-2px);
  box-shadow: 0 0 0 1px var(--transparent-05), 0 8px 20px var(--transparent-07);
}

.step__num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--primary-accent-100);
  color: var(--white);
  font-weight: var(--font-weight-bold);
  font-size: 14px;
}

.step__title {
  font-size: 15px;
  color: var(--grayscale-100);
  margin: 0;
}

.step__body {
  color: var(--grayscale-60);
  font-size: 13px;
  line-height: 1.55;
  margin: 0;
}

/* Examples */
.examples__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 12px;
}

.examples__item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 14px 18px;
  background: var(--white);
  border: 1px solid var(--transparent-05);
  border-left: 3px solid var(--primary-accent-100);
  border-radius: var(--radius-sm);
  font-size: 14px;
  color: var(--grayscale-100);
  line-height: 1.45;
}

.examples__quote {
  font-size: 32px;
  line-height: 0.6;
  color: var(--primary-accent-40);
  font-family: Georgia, 'Times New Roman', serif;
  margin-top: 8px;
}

.examples__text {
  flex: 1;
  font-style: italic;
}

/* CTA */
.cta {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px 24px;
  background: var(--primary-accent-04-transparent);
  border: 1px dashed var(--primary-accent-40);
  border-radius: var(--radius-md);
}

.cta__arrow {
  font-size: 28px;
  color: var(--primary-accent-100);
  animation: arrow-nudge 1.8s ease-in-out infinite;
}
@keyframes arrow-nudge {
  0%, 100% { transform: translateX(0); }
  50%      { transform: translateX(-4px); }
}

.cta__text {
  color: var(--grayscale-100);
  font-size: 14px;
}
.cta__text strong { font-weight: var(--font-weight-semibold); }

@media (max-width: 640px) {
  .welcome { gap: 32px; }
  .hero { padding: 32px 24px; }
  .hero__title { font-size: 36px; }
  .hero__tagline { font-size: 16px; }
  .hero__lead { font-size: 13px; }
  .hero__halo { top: -80px; right: -80px; width: 240px; height: 240px; }

  .step { padding: 18px; }
  .step__title { font-size: 14px; }

  .examples__item { padding: 12px 14px; font-size: 13px; }

  .cta { padding: 16px 18px; gap: 12px; }
  .cta__arrow { font-size: 22px; }
  .cta__text { font-size: 13px; }
}
</style>
