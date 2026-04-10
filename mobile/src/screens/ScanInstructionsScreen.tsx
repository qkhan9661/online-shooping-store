import { NativeStackNavigationProp } from "@react-navigation/native-stack";
import React from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { colors } from "../theme";
import { RootStackParamList } from "../types";

type Props = {
  navigation: NativeStackNavigationProp<RootStackParamList, "ScanInstructions">;
};

export default function ScanInstructionsScreen({ navigation }: Props) {
  return (
    <View style={styles.wrap}>
      <Text style={styles.title}>How to scan</Text>
      <View style={styles.card}>
        <Text style={styles.step}>
          The app speaks each step aloud (voice assistant). You can replay instructions on the capture screen.
        </Text>
        <Text style={styles.step}>1. Full-body front photo — head to feet visible.</Text>
        <Text style={styles.step}>2. Right shoulder, then left shoulder — for clearer shoulder line.</Text>
        <Text style={styles.step}>3. Upper chest framing — helps chest estimate.</Text>
        <Text style={styles.step}>4. Optional side profile — improves confidence when pose is detected.</Text>
        <Text style={styles.step}>5. Enter your real height in centimeters for accurate scaling.</Text>
      </View>
      <Pressable style={styles.primary} onPress={() => navigation.navigate("Capture")}>
        <Text style={styles.primaryText}>Start capture</Text>
      </Pressable>
      <Pressable style={styles.secondary} onPress={() => navigation.navigate("ProductList")}>
        <Text style={styles.secondaryText}>Browse catalog first</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: colors.bg, padding: 24, paddingTop: 56 },
  title: { fontSize: 28, fontWeight: "800", color: colors.text, marginBottom: 16 },
  card: {
    backgroundColor: colors.card,
    borderRadius: 16,
    padding: 20,
    borderWidth: 1,
    borderColor: colors.border,
    gap: 14,
    marginBottom: 24,
  },
  step: { color: colors.muted, lineHeight: 24, fontSize: 15 },
  primary: {
    backgroundColor: colors.accent,
    paddingVertical: 16,
    borderRadius: 12,
    alignItems: "center",
  },
  primaryText: { color: colors.bg, fontWeight: "700", fontSize: 16 },
  secondary: { paddingVertical: 14, alignItems: "center" },
  secondaryText: { color: colors.accent, fontWeight: "600" },
});
