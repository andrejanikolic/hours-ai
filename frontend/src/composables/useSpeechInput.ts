import { onBeforeUnmount, ref } from 'vue'

/**
 * Thin wrapper around the browser Web Speech API (SpeechRecognition).
 *
 * Dictation is fully client-side — no backend, no API key. Supported in
 * Chrome / Edge / Safari (via the `webkit` prefix); `supported` is false in
 * Firefox, so callers should hide the mic affordance when it's false.
 *
 * On every recognition event the consumer receives the *cumulative* finalized
 * transcript for this session plus the current *interim* (still-changing) text,
 * so it can render a live transcript and commit final text as the user speaks.
 */
export interface SpeechInputOptions {
  /** BCP-47 language tag fed to the recognizer. Defaults to `en-US`. */
  lang?: string
  /** Called per result: `final` is the full committed transcript so far, `interim` is the live guess. */
  onresult: (final: string, interim: string) => void
}

type RecognitionCtor = new () => SpeechRecognitionLike

interface SpeechRecognitionLike {
  lang: string
  continuous: boolean
  interimResults: boolean
  start: () => void
  stop: () => void
  onresult: ((e: SpeechRecognitionEventLike) => void) | null
  onerror: ((e: { error: string }) => void) | null
  onend: (() => void) | null
}

interface SpeechRecognitionEventLike {
  resultIndex: number
  results: ArrayLike<{ isFinal: boolean; 0: { transcript: string } }>
}

export function useSpeechInput(opts: SpeechInputOptions) {
  const Ctor = (window as unknown as {
    SpeechRecognition?: RecognitionCtor
    webkitSpeechRecognition?: RecognitionCtor
  }).SpeechRecognition ??
    (window as unknown as { webkitSpeechRecognition?: RecognitionCtor }).webkitSpeechRecognition

  const supported = !!Ctor
  const listening = ref(false)
  const error = ref<string | null>(null)

  let rec: SpeechRecognitionLike | null = null

  function start(): void {
    if (!supported || !Ctor || listening.value) return
    error.value = null

    rec = new Ctor()
    rec.lang = opts.lang ?? 'en-US'
    rec.continuous = true
    rec.interimResults = true

    rec.onresult = (e) => {
      // Rebuild the whole transcript each event — robust against how browsers
      // batch interim vs. final results (appending deltas can drop text).
      let final = ''
      let interim = ''
      for (let i = 0; i < e.results.length; i++) {
        const r = e.results[i]
        if (r.isFinal) final += r[0].transcript
        else interim += r[0].transcript
      }
      opts.onresult(final, interim)
    }
    rec.onerror = (e) => {
      error.value =
        e.error === 'not-allowed' || e.error === 'service-not-allowed'
          ? 'Microphone access blocked — allow it in your browser and try again.'
          : e.error === 'no-speech'
          ? "Didn't catch that — try speaking again."
          : e.error === 'network'
          ? 'Voice service unreachable — check your connection.'
          : 'Speech recognition error — try again.'
      listening.value = false
    }
    rec.onend = () => {
      listening.value = false
    }

    try {
      rec.start()
      listening.value = true
    } catch {
      /* start() throws if already running — ignore. */
    }
  }

  function stop(): void {
    if (rec && listening.value) rec.stop()
  }

  function toggle(): void {
    listening.value ? stop() : start()
  }

  onBeforeUnmount(() => {
    if (rec) {
      rec.onresult = null
      rec.onerror = null
      rec.onend = null
      try {
        rec.stop()
      } catch {
        /* not running */
      }
    }
  })

  return { supported, listening, error, start, stop, toggle }
}
