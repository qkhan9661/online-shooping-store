import { NativeStackNavigationProp } from "@react-navigation/native-stack";
import { CameraView, useCameraPermissions } from "expo-camera";
import React, { useState } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { apiFetch } from "../api";
import { useAuth } from "../auth";
import { colors } from "../theme";
import { RootStackParamList } from "../types";

type Props = {
  navigation: NativeStackNavigationProp<RootStackParamList, "ARTryOn">;
};

/**
 * Phase 3 shell: live camera only. Production path = ARKit / ARCore / Unity cloth
 * native module; keep JS thread thin (no per-frame heavy work here).
 */
export default function ARTryOnScreen({ navigation }: Props) {
  const { token } = useAuth();
  const [permission, requestPermission] = useCameraPermissions();
  const [poseHint, setPoseHint] = useState<string | null>(null);

  const pingPose = async () => {
    if (!token) {
      return;
    }
    const res = await apiFetch("/ai/realtime-pose", token, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ timestamp_ms: Date.now(), device_fps: 30 }),
    });
    const j = await res.json();
    if (res.status === 202 && typeof j.message === "string") {
      setPoseHint(`${j.message.slice(0, 120)}… (Echo: pose.updated)`);
    } else {
      setPoseHint(typeof j.body_state === "string" ? j.body_state : JSON.stringify(j).slice(0, 80));
    }
  };

  if (!permission) {
    return (
      <View style={styles.center}>
        <Text style={styles.muted}>Checking camera…</Text>
      </View>
    );
  }

  if (!permission.granted) {
    return (
      <View style={styles.center}>
        <Text style={styles.title}>Camera required</Text>
        <Pressable style={styles.btn} onPress={requestPermission}>
          <Text style={styles.btnText}>Grant permission</Text>
        </Pressable>
        <Pressable onPress={() => navigation.goBack()}>
          <Text style={styles.link}>Go back</Text>
        </Pressable>
      </View>
    );
  }

  return (
    <View style={styles.wrap}>
      <CameraView style={styles.camera} facing="front" />
      <View style={styles.overlay}>
        <Text style={styles.badge}>Phase 3 · AR shell</Text>
        <Text style={styles.note}>
          Cloth mesh + PhysX / Unity runtime not bundled in Expo JS. Overlay GLB here after native
          integration; target 30–60 FPS on a background thread / native renderer.
        </Text>
        {poseHint ? <Text style={styles.hint}>Pose API: {poseHint}</Text> : null}
        <View style={styles.row}>
          <Pressable style={styles.btn} onPress={pingPose}>
            <Text style={styles.btnText}>Test /api/ai/realtime-pose</Text>
          </Pressable>
          <Pressable style={styles.btnSecondary} onPress={() => navigation.goBack()}>
            <Text style={styles.btnSecondaryText}>Close</Text>
          </Pressable>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: "#000" },
  camera: { flex: 1 },
  overlay: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    padding: 16,
    backgroundColor: "rgba(15,23,42,0.88)",
    gap: 10,
  },
  badge: { color: colors.accent, fontWeight: "800" },
  note: { color: colors.muted, lineHeight: 20, fontSize: 13 },
  hint: { color: colors.text, fontSize: 12 },
  row: { flexDirection: "row", gap: 10, marginTop: 8 },
  btn: { flex: 1, backgroundColor: colors.accent, padding: 14, borderRadius: 12, alignItems: "center" },
  btnText: { color: colors.bg, fontWeight: "700" },
  btnSecondary: {
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
    justifyContent: "center",
  },
  btnSecondaryText: { color: colors.text, fontWeight: "600" },
  center: { flex: 1, backgroundColor: colors.bg, justifyContent: "center", alignItems: "center", padding: 24 },
  title: { color: colors.text, fontSize: 20, fontWeight: "800", marginBottom: 16 },
  muted: { color: colors.muted },
  link: { color: colors.accent, marginTop: 16 },
});
