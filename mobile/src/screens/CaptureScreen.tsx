import { NativeStackNavigationProp } from "@react-navigation/native-stack";
import * as ImagePicker from "expo-image-picker";
import * as Speech from "expo-speech";
import React, { useCallback, useEffect, useRef, useState } from "react";
import {
  Alert,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useAuth } from "../auth";
import { getApiBase } from "../api";
import { CAPTURE_STEPS, CaptureStepId } from "../voice/guidedCapturePhrases";
import { colors } from "../theme";
import { RootStackParamList } from "../types";

type Props = {
  navigation: NativeStackNavigationProp<RootStackParamList, "Capture">;
};

const initialAssets = (): Record<CaptureStepId, ImagePicker.ImagePickerAsset | null> => ({
  full_front: null,
  right_shoulder: null,
  left_shoulder: null,
  chest: null,
  side_profile: null,
});

export default function CaptureScreen({ navigation }: Props) {
  const { token } = useAuth();
  const [stepIndex, setStepIndex] = useState(0);
  const [assets, setAssets] = useState(initialAssets);
  const [height, setHeight] = useState("175");
  const [busy, setBusy] = useState(false);
  const mounted = useRef(true);

  useEffect(() => {
    mounted.current = true;
    return () => {
      mounted.current = false;
      Speech.stop();
    };
  }, []);

  const speakCurrent = useCallback(() => {
    const step = CAPTURE_STEPS[stepIndex];
    if (!step) {
      return;
    }
    Speech.stop();
    Speech.speak(step.voice, {
      language: "en",
      rate: 0.94,
      pitch: 1.0,
    });
  }, [stepIndex]);

  useEffect(() => {
    const t = setTimeout(() => speakCurrent(), 400);
    return () => clearTimeout(t);
  }, [stepIndex, speakCurrent]);

  const setAsset = (id: CaptureStepId, a: ImagePicker.ImagePickerAsset | null) => {
    setAssets((prev) => ({ ...prev, [id]: a }));
  };

  const takePhoto = async (id: CaptureStepId) => {
    const perm = await ImagePicker.requestCameraPermissionsAsync();
    if (!perm.granted) {
      Alert.alert("Permission", "Camera access is needed for capture.");
      return;
    }
    Speech.stop();
    const res = await ImagePicker.launchCameraAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      quality: 0.88,
    });
    if (!res.canceled && res.assets[0]) {
      setAsset(id, res.assets[0]);
    }
  };

  const current = CAPTURE_STEPS[stepIndex];
  const isLast = stepIndex === CAPTURE_STEPS.length - 1;

  const upload = async (opts?: { omitSide?: boolean }) => {
    if (!token) {
      return;
    }
    if (!assets.full_front) {
      Alert.alert("Required", "Please complete step 1 — full front photo.");
      return;
    }
    const h = parseFloat(height.replace(",", "."));
    if (Number.isNaN(h) || h < 120 || h > 230) {
      Alert.alert("Height", "Enter height in centimeters between 120 and 230.");
      return;
    }
    setBusy(true);
    Speech.stop();
    try {
      const form = new FormData();
      form.append("height_cm", String(h));
      const appendFile = (field: string, asset: ImagePicker.ImagePickerAsset, name: string) => {
        const uri = asset.uri;
        const ext = uri.split(".").pop()?.toLowerCase() === "png" ? "png" : "jpg";
        form.append(field, {
          uri,
          name,
          type: ext === "png" ? "image/png" : "image/jpeg",
        } as unknown as Blob);
      };

      appendFile("front_image", assets.full_front, "front.jpg");
      if (assets.right_shoulder) {
        appendFile("right_shoulder_image", assets.right_shoulder, "right_shoulder.jpg");
      }
      if (assets.left_shoulder) {
        appendFile("left_shoulder_image", assets.left_shoulder, "left_shoulder.jpg");
      }
      if (assets.chest) {
        appendFile("chest_detail_image", assets.chest, "chest.jpg");
      }
      if (assets.side_profile && !opts?.omitSide) {
        appendFile("side_image", assets.side_profile, "side.jpg");
      }

      const res = await fetch(`${getApiBase()}/scans`, {
        method: "POST",
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: form,
      });
      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.message ?? "Upload failed");
      }
      const scanId = data.scan?.id;
      if (!scanId) {
        throw new Error("Invalid response");
      }
      navigation.replace("Processing", { scanId });
    } catch (e) {
      Alert.alert("Upload", e instanceof Error ? e.message : "Failed");
    } finally {
      if (mounted.current) {
        setBusy(false);
      }
    }
  };

  if (!current) {
    return null;
  }

  return (
    <ScrollView style={styles.wrap} contentContainerStyle={styles.content}>
      <Text style={styles.title}>Guided capture</Text>
      <Text style={styles.sub}>Voice assistant reads each step. Use headphones in public if you prefer.</Text>

      <Text style={styles.label}>Your height (cm)</Text>
      <TextInput
        style={styles.input}
        keyboardType="decimal-pad"
        value={height}
        onChangeText={setHeight}
        placeholderTextColor={colors.muted}
      />

      <View style={styles.stepCard}>
        <Text style={styles.stepTitle}>{current.title}</Text>
        <Text style={styles.stepHint} numberOfLines={6}>
          {current.voice}
        </Text>
        <View style={styles.row}>
          <Pressable style={styles.secondaryBtn} onPress={speakCurrent}>
            <Text style={styles.secondaryText}>Repeat voice</Text>
          </Pressable>
          <Pressable style={styles.primarySm} onPress={() => takePhoto(current.id)}>
            <Text style={styles.primarySmText}>Open camera</Text>
          </Pressable>
        </View>
        {assets[current.id] ? (
          <Image source={{ uri: assets[current.id]!.uri }} style={styles.thumb} />
        ) : (
          <Text style={styles.missing}>No photo for this step yet.</Text>
        )}
      </View>

      <View style={styles.navRow}>
        <Pressable
          style={[styles.navBtn, stepIndex === 0 && styles.navDisabled]}
          disabled={stepIndex === 0}
          onPress={() => setStepIndex((i) => Math.max(0, i - 1))}
        >
          <Text style={styles.navBtnText}>Back</Text>
        </Pressable>
        {!isLast ? (
          <Pressable style={styles.navBtn} onPress={() => setStepIndex((i) => i + 1)}>
            <Text style={styles.navBtnText}>Next step</Text>
          </Pressable>
        ) : (
          <Pressable
            style={[styles.navBtn, styles.skip, busy && styles.navDisabled]}
            disabled={busy}
            onPress={() => upload()}
          >
            <Text style={styles.navBtnTextOnAccent}>Submit scan</Text>
          </Pressable>
        )}
      </View>

      {current.id === "side_profile" && (
        <Pressable style={styles.ghost} onPress={() => upload({ omitSide: true })} disabled={busy}>
          <Text style={styles.ghostText}>Submit without side photo</Text>
        </Pressable>
      )}

      {!isLast && (
        <Pressable
          style={[styles.bigPrimary, busy && styles.disabled]}
          onPress={() => upload()}
          disabled={busy}
        >
          <Text style={styles.bigPrimaryText}>{busy ? "Uploading…" : "Submit scan now"}</Text>
        </Pressable>
      )}

      <Text style={styles.progress}>
        Step {stepIndex + 1} of {CAPTURE_STEPS.length}
      </Text>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 24, paddingTop: 48, paddingBottom: 48 },
  title: { fontSize: 26, fontWeight: "800", color: colors.text, marginBottom: 8 },
  sub: { color: colors.muted, lineHeight: 22, marginBottom: 16 },
  label: { color: colors.muted, marginBottom: 6 },
  input: {
    backgroundColor: colors.card,
    borderRadius: 12,
    padding: 14,
    color: colors.text,
    borderWidth: 1,
    borderColor: colors.border,
    marginBottom: 20,
  },
  stepCard: {
    backgroundColor: colors.card,
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: colors.border,
    marginBottom: 16,
  },
  stepTitle: { color: colors.accent, fontWeight: "800", fontSize: 16, marginBottom: 10 },
  stepHint: { color: colors.muted, lineHeight: 22, marginBottom: 14 },
  row: { flexDirection: "row", gap: 10, marginBottom: 12 },
  secondaryBtn: {
    flex: 1,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: "center",
  },
  secondaryText: { color: colors.text, fontWeight: "600" },
  primarySm: {
    flex: 1,
    backgroundColor: colors.accent,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: "center",
  },
  primarySmText: { color: colors.bg, fontWeight: "700" },
  thumb: { width: "100%", height: 200, borderRadius: 12, marginTop: 4 },
  missing: { color: colors.muted, fontStyle: "italic", marginTop: 8 },
  navRow: { flexDirection: "row", gap: 12, marginBottom: 12 },
  navBtn: {
    flex: 1,
    backgroundColor: colors.card,
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
    borderWidth: 1,
    borderColor: colors.border,
  },
  navDisabled: { opacity: 0.35 },
  skip: { backgroundColor: colors.accent, borderColor: colors.accent },
  navBtnText: { color: colors.text, fontWeight: "700" },
  navBtnTextOnAccent: { color: colors.bg, fontWeight: "800" },
  ghost: { alignItems: "center", paddingVertical: 10, marginBottom: 8 },
  ghostText: { color: colors.muted, textDecorationLine: "underline" },
  bigPrimary: {
    backgroundColor: colors.accent,
    paddingVertical: 16,
    borderRadius: 12,
    alignItems: "center",
    marginTop: 4,
  },
  bigPrimaryText: { color: colors.bg, fontWeight: "800", fontSize: 16 },
  disabled: { opacity: 0.6 },
  progress: { color: colors.muted, textAlign: "center", marginTop: 16 },
});
