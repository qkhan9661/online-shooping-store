/**
 * Text-to-speech lines for the guided multiview capture flow.
 * Spoken when each step becomes active (and when the user taps "Repeat voice").
 */
export type CaptureStepId =
  | "full_front"
  | "right_shoulder"
  | "left_shoulder"
  | "chest"
  | "side_profile";

export const CAPTURE_STEPS: { id: CaptureStepId; title: string; voice: string }[] = [
  {
    id: "full_front",
    title: "1 · Full body (front)",
    voice:
      "Step one. Step back until your whole body is visible from head to feet. Stand naturally with arms slightly away from your sides. When you are ready, open the camera and take the front photo.",
  },
  {
    id: "right_shoulder",
    title: "2 · Right shoulder",
    voice:
      "Step two. Turn slightly so your right shoulder faces the camera. Keep the shoulder and upper arm clearly in the frame. Take the picture.",
  },
  {
    id: "left_shoulder",
    title: "3 · Left shoulder",
    voice:
      "Step three. Now show your left shoulder to the camera. Keep good lighting and hold still. Capture when ready.",
  },
  {
    id: "chest",
    title: "4 · Chest area",
    voice:
      "Step four. Frame your upper chest and shoulders. Relax your arms. This helps estimate chest width. Take the photo.",
  },
  {
    id: "side_profile",
    title: "5 · Side profile (optional)",
    voice:
      "Step five, optional. Turn ninety degrees for a side profile, full body if you can. Skip if you prefer. Capture the side photo.",
  },
];
