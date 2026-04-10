import { NativeStackNavigationProp } from "@react-navigation/native-stack";
import { RouteProp } from "@react-navigation/native";
import React, { useEffect, useRef } from "react";
import { ActivityIndicator, StyleSheet, Text, View } from "react-native";
import { useAuth } from "../auth";
import { apiFetch } from "../api";
import { colors } from "../theme";
import { RootStackParamList } from "../types";

type Props = {
  navigation: NativeStackNavigationProp<RootStackParamList, "Processing">;
  route: RouteProp<RootStackParamList, "Processing">;
};

export default function ProcessingScreen({ navigation, route }: Props) {
  const { token } = useAuth();
  const { scanId } = route.params;
  const stopped = useRef(false);

  useEffect(() => {
    stopped.current = false;
    const tick = async () => {
      while (!stopped.current && token) {
        const res = await apiFetch(`/scans/${scanId}`, token);
        const data = await res.json();
        if (!res.ok) {
          break;
        }
        if (data.status === "completed" || data.status === "failed") {
          navigation.replace("Results", { scanId });
          return;
        }
        await new Promise((r) => setTimeout(r, 2000));
      }
    };
    tick();
    return () => {
      stopped.current = true;
    };
  }, [navigation, scanId, token]);

  return (
    <View style={styles.wrap}>
      <ActivityIndicator size="large" color={colors.accent} />
      <Text style={styles.title}>Analyzing your photos</Text>
      <Text style={styles.sub}>
        Pose landmarks are extracted and scaled with your height. This usually takes a few seconds.
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flex: 1,
    backgroundColor: colors.bg,
    padding: 24,
    justifyContent: "center",
    alignItems: "center",
    gap: 16,
  },
  title: { color: colors.text, fontSize: 22, fontWeight: "700", textAlign: "center" },
  sub: { color: colors.muted, textAlign: "center", lineHeight: 22, maxWidth: 320 },
});
