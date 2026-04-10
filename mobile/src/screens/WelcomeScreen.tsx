import { NativeStackNavigationProp } from "@react-navigation/native-stack";
import React from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { colors } from "../theme";
import { RootStackParamList } from "../types";

type Props = {
  navigation: NativeStackNavigationProp<RootStackParamList, "Welcome">;
};

export default function WelcomeScreen({ navigation }: Props) {
  return (
    <View style={styles.wrap}>
      <Text style={styles.logo}>FitShop</Text>
      <Text style={styles.tag}>Measure once. Shop with confidence.</Text>
      <View style={styles.card}>
        <Text style={styles.cardTitle}>AI body scan</Text>
        <Text style={styles.cardBody}>
          Upload front and side photos. We estimate key measurements and match you to real size charts.
        </Text>
      </View>
      <Pressable style={styles.primary} onPress={() => navigation.navigate("Auth")}>
        <Text style={styles.primaryText}>Get started</Text>
      </Pressable>
      <Pressable style={styles.secondary} onPress={() => navigation.navigate("Auth")}>
        <Text style={styles.secondaryText}>I already have an account</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flex: 1,
    backgroundColor: colors.bg,
    padding: 24,
    justifyContent: "center",
    gap: 16,
  },
  logo: { fontSize: 36, fontWeight: "800", color: colors.text },
  tag: { fontSize: 16, color: colors.muted, marginBottom: 8 },
  card: {
    backgroundColor: colors.card,
    borderRadius: 16,
    padding: 20,
    borderWidth: 1,
    borderColor: colors.border,
    marginVertical: 8,
  },
  cardTitle: { color: colors.text, fontSize: 18, fontWeight: "700", marginBottom: 8 },
  cardBody: { color: colors.muted, lineHeight: 22 },
  primary: {
    backgroundColor: colors.accent,
    paddingVertical: 16,
    borderRadius: 12,
    alignItems: "center",
    marginTop: 8,
  },
  primaryText: { color: colors.bg, fontWeight: "700", fontSize: 16 },
  secondary: { paddingVertical: 12, alignItems: "center" },
  secondaryText: { color: colors.accent, fontWeight: "600" },
});
