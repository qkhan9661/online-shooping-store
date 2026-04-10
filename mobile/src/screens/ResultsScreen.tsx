import { NativeStackNavigationProp } from "@react-navigation/native-stack";
import { RouteProp } from "@react-navigation/native";
import React, { useEffect, useState } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { useAuth } from "../auth";
import { apiFetch } from "../api";
import { colors } from "../theme";
import { RootStackParamList } from "../types";

type Props = {
  navigation: NativeStackNavigationProp<RootStackParamList, "Results">;
  route: RouteProp<RootStackParamList, "Results">;
};

type ScanPayload = {
  status: string;
  error_message?: string;
  ai_confidence?: number;
  measurement?: {
    chest_cm: string;
    waist_cm: string;
    hips_cm: string;
    shoulder_width_cm: string;
    confidence: string;
  };
};

export default function ResultsScreen({ navigation, route }: Props) {
  const { token } = useAuth();
  const { scanId } = route.params;
  const [data, setData] = useState<ScanPayload | null>(null);

  useEffect(() => {
    if (!token) {
      return;
    }
    (async () => {
      const res = await apiFetch(`/scans/${scanId}`, token);
      setData(await res.json());
    })();
  }, [scanId, token]);

  if (!data) {
    return (
      <View style={styles.wrap}>
        <Text style={styles.muted}>Loading…</Text>
      </View>
    );
  }

  if (data.status === "failed") {
    return (
      <View style={styles.wrap}>
        <Text style={styles.title}>Scan failed</Text>
        <Text style={styles.muted}>{data.error_message ?? "Unknown error"}</Text>
        <Pressable style={styles.primary} onPress={() => navigation.navigate("Capture")}>
          <Text style={styles.primaryText}>Try again</Text>
        </Pressable>
      </View>
    );
  }

  const m = data.measurement;

  return (
    <View style={styles.wrap}>
      <Text style={styles.title}>Your measurements</Text>
      <Text style={styles.conf}>
        Model confidence: {data.ai_confidence != null ? `${Math.round(data.ai_confidence * 100)}%` : "—"}
      </Text>
      {m ? (
        <View style={styles.grid}>
          <Metric label="Chest" value={`${m.chest_cm} cm`} />
          <Metric label="Waist" value={`${m.waist_cm} cm`} />
          <Metric label="Hips" value={`${m.hips_cm} cm`} />
          <Metric label="Shoulders" value={`${m.shoulder_width_cm} cm`} />
        </View>
      ) : (
        <Text style={styles.muted}>Measurements not ready yet.</Text>
      )}
      <Pressable style={styles.primary} onPress={() => navigation.navigate("ProductList")}>
        <Text style={styles.primaryText}>Shop with fit</Text>
      </Pressable>
    </View>
  );
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.metric}>
      <Text style={styles.metricLabel}>{label}</Text>
      <Text style={styles.metricValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: colors.bg, padding: 24, paddingTop: 56 },
  title: { fontSize: 26, fontWeight: "800", color: colors.text, marginBottom: 8 },
  conf: { color: colors.muted, marginBottom: 20 },
  grid: { flexDirection: "row", flexWrap: "wrap", gap: 12, marginBottom: 24 },
  metric: {
    width: "47%",
    backgroundColor: colors.card,
    borderRadius: 14,
    padding: 16,
    borderWidth: 1,
    borderColor: colors.border,
  },
  metricLabel: { color: colors.muted, fontSize: 13, marginBottom: 4 },
  metricValue: { color: colors.text, fontSize: 18, fontWeight: "700" },
  muted: { color: colors.muted },
  primary: {
    backgroundColor: colors.accent,
    paddingVertical: 16,
    borderRadius: 12,
    alignItems: "center",
    marginTop: 12,
  },
  primaryText: { color: colors.bg, fontWeight: "700", fontSize: 16 },
});
