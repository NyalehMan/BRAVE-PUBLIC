import { ref } from 'vue'
import { publicAssetUrl } from '@/utils/publicAssetUrl'

const soundEnabled = ref(localStorage.getItem('braveSoundEnabled') !== 'false')
const selectedSound = ref(localStorage.getItem('braveAlertSound') || 'chime')

const alertSounds = {
  chime: publicAssetUrl('sounds/alert-chime.mp3'),
  siren: publicAssetUrl('sounds/emergency-siren.mp3'),
  notification: publicAssetUrl('sounds/notification.mp3'),
}

let alertAudio = null
let audioUnlocked = false

function getSoundSrc(type = selectedSound.value) {
  return alertSounds[type] || alertSounds.chime
}

async function unlockAudio(type = selectedSound.value) {
  try {
    alertAudio = new Audio(getSoundSrc(type))
    alertAudio.preload = 'auto'
    alertAudio.volume = 0.01

    await alertAudio.play()
    alertAudio.pause()
    alertAudio.currentTime = 0

    audioUnlocked = true
  } catch (err) {
    audioUnlocked = false
    console.warn('Audio unlock failed:', err)
  }
}

async function toggleSound() {
  soundEnabled.value = !soundEnabled.value
  localStorage.setItem('braveSoundEnabled', String(soundEnabled.value))

  if (soundEnabled.value) {
    await unlockAudio()
  }
}

async function changeAlertSound(type) {
  selectedSound.value = type
  localStorage.setItem('braveAlertSound', type)

  if (soundEnabled.value) {
    await unlockAudio(type)
  }
}

async function playSpecificAlertSound(type = selectedSound.value) {
  if (!soundEnabled.value) return

  try {
    if (!alertAudio || !audioUnlocked) {
      await unlockAudio(type)
    }

    alertAudio.src = getSoundSrc(type)
    alertAudio.volume = type === 'siren' ? 0.45 : 0.25
    alertAudio.currentTime = 0

    await alertAudio.play()
  } catch (err) {
    console.warn('Sound blocked:', err)
  }
}

function playAlertSound() {
  playSpecificAlertSound(selectedSound.value)
}

export function useAlertSound() {
  return {
    soundEnabled,
    selectedSound,
    toggleSound,
    changeAlertSound,
    playAlertSound,
    playSpecificAlertSound,
    unlockAudio,
  }
}
