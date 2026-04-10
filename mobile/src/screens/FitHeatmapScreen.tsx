import { NativeStackNavigationProp } from "@react-navigation/native-stack";
import { RouteProp } from "@react-navigation/native";
import React from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { colors } from "../theme";
import { RootStackParamList } from "../types";

type Props = {
  navigation: NativeStackNavigationProp<RootStackParamList, "FitHeatmap">;
  route: RouteProp<RootStackParamList, "FitHeatmap">;
};

/**
 * Phase 3: map FitPredictionJob output to red/green/blue heatmap overlay on mesh or 2D silhouette.
 */
export default function FitHeatmapScreen({ navigation, route }: Props) {
  const { garmentId } = route.params;
  return (
    <View style={styles.wrap}>
      <Text style={styles.title}>AI fit heatmap</Text>
      <Text style={styles.body}>Garment #{garmentId}</Text>
      <View style={styles.legend}>
        <Legend color="#f87171" label="Tight" />
        <Legend color="#4ade80" label="Ideal" />
        <Legend color="#60a5fa" label="Loose" />
      </View>
      <Text style={styles.muted}>
        Backend: dispatch App\Jobs\Vton\FitPredictionJob; store per-zone scores; render as texture or
        Skia mesh colors.
      </Text>
      <Pressable style={styles.btn} onPress={() => navigation.goBack()}>
        <Text style={styles.btnText}>Back</Text>
      </Pressable>
    </View>
  );
}

function Legend({ color, label }: { color: string; label: string }) {
  return (
    <View style={styles.legendRow}>
      <View style={[styles.swatch, { backgroundColor: color }]} />
      <Text style={styles.legendText}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: colors.bg, padding: 24, paddingTop: 56 },
  title: { fontSize: 24, fontWeight: "800", color: colors.text, marginBottom: 8 },
  body: { color: colors.text, marginBottom: 20 },
  legend: { gap: 10, marginBottom: 20 },
  legendRow: { flexDirection: "row", alignItems: "center", gap: 10 },
  swatch: { width: 22, height: 22, borderRadius: 4 },
  legendText: { color: colors.muted },
  muted: { color: colors.muted, lineHeight: 22, marginBottom: 24 },
  btn: {
    alignSelf: "flex-start",
    backgroundColor: colors.accent,
    paddingHorizontal: 20,
    paddingVertical: 14,
    borderRadius: 12,
  },
  btnText: { color: colors.bg, fontWeight: "700" },
});
