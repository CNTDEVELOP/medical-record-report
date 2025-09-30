import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'

/**
 * ธีม Light only โทนฟ้า:
 * - เซ็ตพาเลต primary เป็น {blue} ทั้งชุด (50–950)
 * - ระบุ primary.color / hover / active ในโหมด light
 * - ไม่รองรับ dark-mode (เราจะตั้ง darkModeSelector เป็นคลาสที่เราไม่เคยใส่)
 */
export const DentalBlue = definePreset(Aura, {
  semantic: {
    // ใช้พาเลตฟ้าของ PrimeVue เป็น primary ทั้งสเกล
    primary: {
      50:  '{blue.50}',
      100: '{blue.100}',
      200: '{blue.200}',
      300: '{blue.300}',
      400: '{blue.400}',
      500: '{blue.500}',
      600: '{blue.600}',
      700: '{blue.700}',
      800: '{blue.800}',
      900: '{blue.900}',
      950: '{blue.950}'
    },
    // บังคับค่าหลักโหมด light (โทนปุ่ม ฯลฯ)
    colorScheme: {
      light: {
        primary: {
          color: '{blue.600}',        // สีหลัก (เช่น ปุ่ม)
          inverseColor: '#ffffff',    // สีตัวอักษรบนพื้น primary
          hoverColor: '{blue.700}',
          activeColor: '{blue.800}'
        },
        // (ทางเลือก) เน้นโทน highlight เช่น selected/hover row
        highlight: {
          background: '{blue.50}',
          focusBackground: '{blue.100}',
          color: '{blue.700}',
          focusColor: '{blue.800}'
        }
      }
      // ไม่กำหนด dark เพื่อหลีกเลี่ยงการสร้างโทนมืด
    }
  }
})
