import { NativeStackNavigationProp } from "@react-navigation/native-stack";
import { RouteProp } from "@react-navigation/native";
import React, { useCallback, useEffect, useState } from "react";
import {
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useAuth } from "../auth";
import { apiFetch } from "../api";
import { colors } from "../theme";
import { RootStackParamList } from "../types";

type Fit = {
  status: string;
  recommended_size: string | null;
  fit_notes: string | null;
  confidence: string | null;
};

type GarmentDetail = {
  id: number;
  name: string;
  sku: string;
  description: string | null;
  price: string;
  image_url: string | null;
  size_chart: Record<string, Record<string, number>>;
  fit_recommendation: Fit | null;
  vton?: {
    asset_pipeline_status: string;
    mesh_glb_url: string | null;
    mesh_ar_lod_url: string | null;
    mesh_cdn_url: string | null;
    physics_profile: Record<string, unknown> | null;
  };
};

type Props = {
  navigation: NativeStackNavigationProp<RootStackParamList, "ProductDetail">;
  route: RouteProp<RootStackParamList, "ProductDetail">;
};

export default function ProductDetailScreen({ route, navigation }: Props) {
  const { garmentId } = route.params;
  const { token } = useAuth();
  const [g, setG] = useState<GarmentDetail | null>(null);
  const [loadingFit, setLoadingFit] = useState(false);

  const load = useCallback(async () => {
    if (!token) {
      return;
    }
    const res = await apiFetch(`/garments/${garmentId}`, token);
    setG(await res.json());
  }, [garmentId, token]);

  useEffect(() => {
    load();
  }, [load]);

  const requestFit = async () => {
    if (!token) {
      return;
    }
    setLoadingFit(true);
    await apiFetch(`/garments/${garmentId}/recommend`, token, { method: "POST" });
    const poll = async () => {
      for (let i = 0; i < 15; i++) {
        await new Promise((r) => setTimeout(r, 1500));
        const res = await apiFetch(`/garments/${garmentId}/fit-recommendation`, token);
        if (res.ok) {
          const row = await res.json();
          if (row.status === "completed") {
            await load();
            setLoadingFit(false);
            return;
          }
        }
      }
      setLoadingFit(false);
      await load();
    };
    poll();
  };

  if (!g) {
    return (
      <View style={styles.center}>
        <Text style={styles.muted}>Loading…</Text>
      </View>
    );
  }

  return (
    <ScrollView style={styles.wrap} contentContainerStyle={{ paddingBottom: 40 }}>
      {g.image_url ? (
        <Image source={{ uri: g.image_url }} style={styles.hero} />
      ) : (
        <View style={[styles.hero, styles.heroPlaceholder]}>
          <Text style={styles.heroPhText}>No image</Text>
        </View>
      )}
      <View style={styles.body}>
        <Text style={styles.title}>{g.name}</Text>
        <Text style={styles.sku}>{g.sku}</Text>
        <Text style={styles.price}>${g.price}</Text>
        {g.description ? <Text style={styles.desc}>{g.description}</Text> : null}

        <View style={styles.fitCard}>
          <Text style={styles.fitTitle}>Size recommendation</Text>
          {g.fit_recommendation?.status === "completed" && g.fit_recommendation.recommended_size ? (
            <>
              <Text style={styles.fitSize}>Suggested: {g.fit_recommendation.recommended_size}</Text>
              <Text style={styles.fitNotes}>{g.fit_recommendation.fit_notes}</Text>
              <Text style={styles.fitConf}>
                Confidence:{" "}
                {g.fit_recommendation.confidence
                  ? `${Math.round(parseFloat(g.fit_recommendation.confidence) * 100)}%`
                  : "—"}
              </Text>
            </>
          ) : (
            <Text style={styles.muted}>
              {g.fit_recommendation?.status === "pending" || g.fit_recommendation?.status === "processing"
                ? "Computing fit…"
                : "Run a body scan, then generate a fit for this item."}
            </Text>
          )}
          <Pressable
            style={[styles.primary, loadingFit && styles.disabled]}
            onPress={requestFit}
            disabled={loadingFit}
          >
            <Text style={styles.primaryText}>{loadingFit ? "Working…" : "Refresh recommendation"}</Text>
          </Pressable>
        </View>

        <View style={styles.phase3}>
          <Text style={styles.phase3Title}>Virtual try-on (Phase 3)</Text>
          <Text style={styles.muted}>
            Status: {g.vton?.asset_pipeline_status ?? "unknown"} · GLB URLs appear when pipeline completes.
          </Text>
          <View style={styles.phase3Row}>
            <Pressable style={styles.phase3Btn} onPress={() => navigation.navigate("ARTryOn")}>
              <Text style={styles.phase3BtnText}>Live AR shell</Text>
            </Pressable>
            <Pressable
              style={styles.phase3Btn}
              onPress={() => navigation.navigate("PhysicsPreview", { garmentId: g.id })}
            >
              <Text style={styles.phase3BtnText}>Physics</Text>
            </Pressable>
            <Pressable
              style={styles.phase3Btn}
              onPress={() => navigation.navigate("FitHeatmap", { garmentId: g.id })}
            >
              <Text style={styles.phase3BtnText}>Heatmap</Text>
            </Pressable>
          </View>
        </View>

        <Text style={styles.chartTitle}>Size chart (cm)</Text>
        {Object.entries(g.size_chart ?? {}).map(([size, dims]) => (
          <View key={size} style={styles.row}>
            <Text style={styles.sizeLabel}>{size}</Text>
            <Text style={styles.sizeDims}>
              {Object.entries(dims)
                .map(([k, v]) => `${k}: ${v}`)
                .join(" · ")}
            </Text>
          </View>
        ))}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: colors.bg },
  center: { flex: 1, backgroundColor: colors.bg, justifyContent: "center", alignItems: "center" },
  hero: { width: "100%", height: 220, backgroundColor: colors.card },
  heroPlaceholder: { justifyContent: "center", alignItems: "center" },
  heroPhText: { color: colors.muted },
  body: { padding: 20 },
  title: { fontSize: 24, fontWeight: "800", color: colors.text },
  sku: { color: colors.muted, marginTop: 4 },
  price: { color: colors.accent, fontSize: 22, fontWeight: "800", marginTop: 8 },
  desc: { color: colors.muted, marginTop: 12, lineHeight: 22 },
  fitCard: {
    marginTop: 24,
    backgroundColor: colors.card,
    borderRadius: 16,
    padding: 18,
    borderWidth: 1,
    borderColor: colors.border,
    gap: 8,
  },
  fitTitle: { color: colors.text, fontWeight: "700", fontSize: 16 },
  fitSize: { color: colors.success, fontSize: 20, fontWeight: "800" },
  fitNotes: { color: colors.muted, lineHeight: 20 },
  fitConf: { color: colors.text, fontSize: 14 },
  muted: { color: colors.muted, lineHeight: 20 },
  primary: {
    backgroundColor: colors.accent,
    paddingVertical: 14,
    borderRadius: 12,
    alignItems: "center",
    marginTop: 12,
  },
  disabled: { opacity: 0.6 },
  primaryText: { color: colors.bg, fontWeight: "700" },
  phase3: {
    marginTop: 20,
    backgroundColor: colors.card,
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: colors.border,
    gap: 10,
  },
  phase3Title: { color: colors.text, fontWeight: "800", fontSize: 16 },
  phase3Row: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  phase3Btn: {
    backgroundColor: "#334155",
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 10,
  },
  phase3BtnText: { color: colors.text, fontWeight: "700", fontSize: 12 },
  chartTitle: { color: colors.text, fontWeight: "700", marginTop: 28, marginBottom: 12 },
  row: {
    flexDirection: "row",
    gap: 12,
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  sizeLabel: { color: colors.accent, width: 36, fontWeight: "700" },
  sizeDims: { flex: 1, color: colors.muted, fontSize: 13 },
});
