<script setup lang="ts">
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue'

import { usePreferredReducedMotion } from '@vueuse/core'

type DemoForm = {
  name: string
  email: string
  company: string
  use_case: string
  monthly_volume: string
}

const featureCards = [
  {
    eyebrow: 'Grounded answers',
    title: 'Ask documents like they were trained into memory.',
    body: 'MemoDoc keeps retrieval tied to the exact pages, passages, and concepts behind each answer so your team can move fast without guessing.',
  },
  {
    eyebrow: 'OCR-ready ingestion',
    title: 'Turn messy scans into structured context.',
    body: 'Invoices, manuals, decks, contracts, and research PDFs become searchable, chunked, and linked into one continuously queryable workspace.',
  },
  {
    eyebrow: 'Memory graph',
    title: 'Connect files, entities, and recurring questions.',
    body: 'Create a layer of reusable knowledge across uploads instead of treating each PDF like a one-off extraction job.',
  },
]

const workflowSteps = [
  {
    step: '01',
    title: 'Drop in PDFs',
    body: 'Bring scans, exported decks, handbooks, and reports into one ingestion stream.',
  },
  {
    step: '02',
    title: 'Enrich with AI',
    body: 'Run OCR, segmentation, semantic indexing, and entity linking behind the scenes.',
  },
  {
    step: '03',
    title: 'Query with confidence',
    body: 'Get grounded answers, citations, and reusable memory instead of brittle keyword search.',
  },
]

const useCases = [
  'Research teams synthesizing dense PDFs into reusable insight',
  'Operations teams searching SOPs, invoices, and internal manuals',
  'Founders and analysts building a live memory layer across documents',
  'Client-facing teams retrieving exact answers from proposal and contract archives',
]

const faqItems = [
  {
    question: 'Does MemoDoc work with scanned PDFs?',
    answer: 'Yes. The landing experience positions MemoDoc around OCR-first ingestion so image-based and messy files can still become searchable AI context.',
  },
  {
    question: 'Can answers stay grounded in the source documents?',
    answer: 'That is the point of the product story. Answers are meant to trace back to the document memory layer rather than free-floating summaries.',
  },
  {
    question: 'Is this built for single files or long-running knowledge bases?',
    answer: 'The page speaks to both, but the stronger angle is a persistent memory layer across many PDFs instead of isolated one-off uploads.',
  },
]

const currentYear = new Date().getFullYear()

const demoForm = reactive<DemoForm>({
  name: '',
  email: '',
  company: '',
  use_case: '',
  monthly_volume: '',
})

const demoSection = ref<HTMLElement | null>(null)
const isSubmitting = ref(false)
const isSuccess = ref(false)
const submitError = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
const observer = ref<IntersectionObserver | null>(null)
const preferredMotion = usePreferredReducedMotion()

function scrollToDemo() {
  demoSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

async function submitDemoRequest() {
  isSubmitting.value = true
  isSuccess.value = false
  submitError.value = ''
  fieldErrors.value = {}

  try {
    const csrfToken = document
      .querySelector('meta[name="csrf-token"]')
      ?.getAttribute('content')

    const response = await fetch('/demo-request', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken ?? '',
      },
      body: JSON.stringify(demoForm),
    })

    const payload = await response.json().catch(() => ({}))

    if (!response.ok) {
      fieldErrors.value = payload.errors ?? {}
      submitError.value = payload.message ?? 'Something went wrong while sending your request.'

      return
    }

    isSuccess.value = true
    demoForm.name = ''
    demoForm.email = ''
    demoForm.company = ''
    demoForm.use_case = ''
    demoForm.monthly_volume = ''
  }
  catch {
    submitError.value = 'Network error. Please try again in a moment.'
  }
  finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  const revealNodes = Array.from(document.querySelectorAll<HTMLElement>('[data-reveal]'))

  if (preferredMotion.value === 'reduce') {
    revealNodes.forEach(node => node.classList.add('is-visible'))

    return
  }

  observer.value = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting)
        return

      entry.target.classList.add('is-visible')
      observer.value?.unobserve(entry.target)
    })
  }, {
    threshold: 0.18,
    rootMargin: '0px 0px -8% 0px',
  })

  revealNodes.forEach(node => observer.value?.observe(node))
})

onBeforeUnmount(() => {
  observer.value?.disconnect()
})
</script>

<template>
  <div class="landing-page">
    <header class="landing-nav">
      <div class="brand-mark">
        <img src="/images/logo.png" alt="MemoDoc" class="brand-mark__logo" />
      </div>

      <nav class="landing-nav__links">
        <a href="#workflow">Workflow</a>
        <a href="#features">Features</a>
        <a href="#faq">FAQ</a>
        <!-- <a href="/admin">Admin</a> -->
      </nav>

      <button class="button button--ghost" type="button" @click="scrollToDemo">
        Book a demo
      </button>
    </header>

    <main>
      <section class="hero-shell">
        <div class="hero-copy reveal" data-reveal>
          <p class="section-kicker">Document intelligence, redesigned</p>
          <h1>Build a living AI memory layer across every PDF your team touches.</h1>
          <p class="hero-copy__body">
            MemoDoc ingests scans, manuals, contracts, and research decks, then turns them into searchable,
            grounded, reusable context with the feel of a modern AI workspace instead of a file graveyard.
          </p>

          <div class="hero-actions">
            <button class="button" type="button" @click="scrollToDemo">
              Start the demo flow
            </button>
            <a class="button button--secondary" href="#features">Explore the product story</a>
          </div>

          <ul class="hero-signals">
            <li>OCR-ready ingestion</li>
            <li>Grounded retrieval</li>
            <li>Persistent memory graph</li>
          </ul>
        </div>

        <div class="hero-visual reveal" data-reveal>
          <div class="neural-orbit neural-orbit--outer"></div>
          <div class="neural-orbit neural-orbit--inner"></div>
          <div class="beam beam--vertical"></div>
          <div class="beam beam--horizontal"></div>

          <article class="glass-panel glass-panel--primary">
            <p class="glass-panel__label">Live document memory</p>
            <h2>Ask for the exact paragraph behind the answer.</h2>
            <div class="query-line">
              <span class="query-line__prompt">Query</span>
              <span class="query-line__text">Summarize renewal risks across vendor contracts.</span>
            </div>
            <div class="answer-card">
              <p>Answer stitched from 12 passages with section-aware retrieval and citation-ready context.</p>
            </div>
          </article>

          <div class="pdf-stack">
            <div class="pdf-card pdf-card--front">
              <span>PDF</span>
              <strong>Board-Pack-Q2.pdf</strong>
              <small>Indexed into semantic memory</small>
            </div>
            <div class="pdf-card pdf-card--mid">
              <span>Scan</span>
              <strong>Ops-Manual-Rev7.pdf</strong>
            </div>
            <div class="pdf-card pdf-card--rear">
              <span>Contract</span>
              <strong>Vendor-Renewals.pdf</strong>
            </div>
          </div>

          <div class="signal-cluster">
            <span></span>
            <span></span>
            <span></span>
          </div>
        </div>
      </section>

      <section class="marquee-band reveal" data-reveal>
        <div class="marquee-track">
          <span>PDF reasoning</span>
          <span>citation-aware answers</span>
          <span>OCR pipelines</span>
          <span>semantic search</span>
          <span>document memory</span>
          <span>AI workspace</span>
        </div>
      </section>

      <section id="workflow" class="section-grid section-grid--workflow">
        <div class="section-heading reveal" data-reveal>
          <p class="section-kicker">How it works</p>
          <h2>From static files to a system that remembers what your documents mean.</h2>
        </div>

        <div class="workflow-grid">
          <article v-for="item in workflowSteps" :key="item.step" class="workflow-card reveal" data-reveal>
            <p class="workflow-card__step">{{ item.step }}</p>
            <h3>{{ item.title }}</h3>
            <p>{{ item.body }}</p>
          </article>
        </div>
      </section>

      <section id="features" class="section-grid section-grid--features">
        <div class="section-heading reveal" data-reveal>
          <p class="section-kicker">Why it feels different</p>
          <h2>Designed like a modern AI product, not a dressed-up document upload form.</h2>
        </div>

        <div class="feature-grid">
          <article v-for="item in featureCards" :key="item.title" class="feature-card reveal" data-reveal>
            <p class="feature-card__eyebrow">{{ item.eyebrow }}</p>
            <h3>{{ item.title }}</h3>
            <p>{{ item.body }}</p>
          </article>
        </div>
      </section>

      <section class="section-grid section-grid--split">
        <article class="story-panel reveal" data-reveal>
          <p class="section-kicker">Use cases</p>
          <h2>Built for teams whose best knowledge is trapped in PDFs.</h2>
          <ul class="use-case-list">
            <li v-for="item in useCases" :key="item">{{ item }}</li>
          </ul>
        </article>

        <article class="story-panel story-panel--pricing reveal" data-reveal>
          <p class="section-kicker">Pricing teaser</p>
          <h2>Start with a focused pilot, then expand into a durable knowledge layer.</h2>
          <p>
            Begin with the documents that already slow your team down the most, validate retrieval quality,
            then scale MemoDoc into a broader document memory system.
          </p>
          <button class="button" type="button" @click="scrollToDemo">Talk through a pilot</button>
        </article>
      </section>

      <section ref="demoSection" class="demo-section reveal" data-reveal>
        <div class="demo-section__copy">
          <p class="section-kicker">Book a demo</p>
          <h2>Tell us what kind of document chaos you want MemoDoc to clean up.</h2>
          <p>
            We will use this to shape a pilot around your PDFs, retrieval needs, and the workflows where precise
            answers matter most.
          </p>
        </div>

        <form class="demo-form" @submit.prevent="submitDemoRequest">
          <label>
            <span>Name</span>
            <input v-model="demoForm.name" type="text" name="name" placeholder="Ada Lovelace" />
            <small v-if="fieldErrors.name">{{ fieldErrors.name[0] }}</small>
          </label>

          <label>
            <span>Email</span>
            <input v-model="demoForm.email" type="email" name="email" placeholder="ada@company.com" />
            <small v-if="fieldErrors.email">{{ fieldErrors.email[0] }}</small>
          </label>

          <label>
            <span>Company</span>
            <input v-model="demoForm.company" type="text" name="company" placeholder="Memo Systems" />
            <small v-if="fieldErrors.company">{{ fieldErrors.company[0] }}</small>
          </label>

          <label>
            <span>Monthly document volume</span>
            <input
              v-model="demoForm.monthly_volume"
              type="text"
              name="monthly_volume"
              placeholder="Example: 2,000 PDFs/month"
            />
            <small v-if="fieldErrors.monthly_volume">{{ fieldErrors.monthly_volume[0] }}</small>
          </label>

          <label class="demo-form__full">
            <span>Primary use case</span>
            <textarea
              v-model="demoForm.use_case"
              name="use_case"
              rows="5"
              placeholder="Tell us about the document types, questions, or workflows you want to improve."
            ></textarea>
            <small v-if="fieldErrors.use_case">{{ fieldErrors.use_case[0] }}</small>
          </label>

          <div class="demo-form__footer demo-form__full">
            <button class="button" type="submit" :disabled="isSubmitting">
              {{ isSubmitting ? 'Sending request...' : 'Send demo request' }}
            </button>

            <p v-if="isSuccess" class="form-status form-status--success">
              Request received. We will follow up with a tailored demo flow.
            </p>

            <p v-if="submitError" class="form-status form-status--error">
              {{ submitError }}
            </p>
          </div>
        </form>
      </section>

      <section id="faq" class="faq-section section-grid">
        <div class="section-heading reveal" data-reveal>
          <p class="section-kicker">FAQ</p>
          <h2>Questions teams ask before replacing static file search with AI memory.</h2>
        </div>

        <div class="faq-list">
          <article v-for="item in faqItems" :key="item.question" class="faq-item reveal" data-reveal>
            <h3>{{ item.question }}</h3>
            <p>{{ item.answer }}</p>
          </article>
        </div>
      </section>

      <footer class="landing-footer reveal" data-reveal>
        <div class="landing-footer__brand">
          <img src="/images/logo.png" alt="MemoDoc" class="landing-footer__logo" />
          <p>AI memory for document-heavy teams that need grounded, reusable answers.</p>
        </div>

        <div class="landing-footer__links">
          <a href="#workflow">Workflow</a>
          <a href="#features">Features</a>
          <a href="#faq">FAQ</a>
          <a href="/privacy-policy">Privacy policy</a>
          <a href="/contact-us">Contact us</a>
          <button class="landing-footer__cta" type="button" @click="scrollToDemo">
            Book a demo
          </button>
        </div>

        <p class="landing-footer__meta">© {{ currentYear }} MemoDoc. All rights reserved.</p>
      </footer>
    </main>
  </div>
</template>
