<template>
  <div class="hours-ai">
    <h2>HoursAI</h2>
    <p class="subtitle">Describe your store hours in plain English</p>

    <div class="input-section">
      <textarea
        v-model="inputText"
        placeholder="e.g. We're open Mon–Fri 8am to 10pm, Saturday 9am to 11pm, closed Sundays."
        rows="3"
        :disabled="loading"
      />
      <button @click="parse" :disabled="loading || !inputText.trim()">
        {{ loading ? 'Parsing...' : 'Parse' }}
      </button>
    </div>

    <div v-if="error" class="error">{{ error }}</div>

    <div v-if="parsed" class="preview">
      <h3>Preview</h3>

      <div v-if="parsed.clarification_needed" class="clarification">
        Could not fully understand the request. Please be more specific.
      </div>

      <template v-else>
        <table>
          <thead>
            <tr>
              <th>Day</th>
              <th>Open</th>
              <th>Close</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="day in parsed.days" :key="day.day">
              <td>{{ capitalize(day.day) }}</td>
              <td>{{ day.closed ? '—' : day.open }}</td>
              <td>{{ day.closed ? '—' : day.close }}</td>
              <td :class="day.closed ? 'closed' : 'open'">
                {{ day.closed ? 'Closed' : 'Open' }}
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="parsed.specialClosures?.length" class="closures">
          <strong>Special closures:</strong>
          <ul>
            <li v-for="c in parsed.specialClosures" :key="c.date">
              {{ c.date }} — {{ c.reason }}
            </li>
          </ul>
        </div>

        <div v-if="parsed.orderCutoffMinutes" class="setting">
          Order cutoff: {{ parsed.orderCutoffMinutes }} minutes before close
        </div>

        <div v-if="parsed.deliveryWindow" class="setting">
          Delivery: {{ parsed.deliveryWindow.open }} – {{ parsed.deliveryWindow.close }}
        </div>

        <div class="actions">
          <button class="btn-apply" @click="apply" :disabled="applying">
            {{ applying ? 'Applying...' : 'Apply' }}
          </button>
          <button class="btn-edit" @click="reset">Edit</button>
        </div>
      </template>
    </div>

    <div v-if="success" class="success">Hours updated successfully!</div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{ storeId: number }>()

const inputText = ref('')
const loading   = ref(false)
const applying  = ref(false)
const parsed    = ref<any>(null)
const error     = ref('')
const success   = ref(false)

const API_BASE = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api'

async function parse() {
  error.value   = ''
  success.value = false
  loading.value = true

  try {
    const res = await fetch(`${API_BASE}/stores/${props.storeId}/hours/parse`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body:    JSON.stringify({ text: inputText.value }),
    })

    if (!res.ok) throw new Error(`Server error: ${res.status}`)

    parsed.value = await res.json()
  } catch (e: any) {
    error.value = e.message ?? 'Something went wrong. Please try again.'
  } finally {
    loading.value = false
  }
}

async function apply() {
  applying.value = true
  error.value    = ''

  try {
    const res = await fetch(`${API_BASE}/stores/${props.storeId}/hours`, {
      method:  'PATCH',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body:    JSON.stringify(parsed.value),
    })

    if (!res.ok) {
      const body = await res.json()
      throw new Error(body.message ?? `Server error: ${res.status}`)
    }

    success.value = true
    parsed.value  = null
    inputText.value = ''
  } catch (e: any) {
    error.value = e.message ?? 'Failed to apply changes.'
  } finally {
    applying.value = false
  }
}

function reset() {
  parsed.value  = null
  error.value   = ''
  success.value = false
}

function capitalize(s: string) {
  return s.charAt(0).toUpperCase() + s.slice(1)
}
</script>

<style scoped>
.hours-ai { max-width: 640px; margin: 0 auto; font-family: sans-serif; padding: 24px; }
.subtitle { color: #666; margin-bottom: 16px; }
.input-section { display: flex; gap: 8px; margin-bottom: 16px; }
textarea { flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; resize: vertical; }
button { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; background: #4f46e5; color: white; }
button:disabled { opacity: 0.5; cursor: not-allowed; }
.preview { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-top: 16px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
th, td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
th { font-weight: 600; background: #f9fafb; }
.open { color: #16a34a; }
.closed { color: #dc2626; }
.closures, .setting { margin: 8px 0; font-size: 14px; }
.actions { display: flex; gap: 8px; margin-top: 16px; }
.btn-apply { background: #16a34a; }
.btn-edit { background: #6b7280; }
.error { color: #dc2626; padding: 10px; background: #fef2f2; border-radius: 6px; margin-top: 8px; }
.success { color: #16a34a; padding: 10px; background: #f0fdf4; border-radius: 6px; margin-top: 16px; }
.clarification { color: #d97706; padding: 10px; background: #fffbeb; border-radius: 6px; }
</style>
