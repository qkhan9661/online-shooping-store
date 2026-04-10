import { NativeStackNavigationProp } from "@react-navigation/native-stack";
import { RouteProp } from "@react-navigation/native";
import React from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { colors } from "../theme";
import { RootStackParamList } from "../types";

type Props = {
  navigation: NativeStackNavigationProp<RootStackParamList, "PhysicsPreview">;
  route: RouteProp<RootStackParamList, "PhysicsPreview">;
};

/**
 * Phase 3 placeholder for pre-baked cloth simulation clips or Unity embedded view.
 */
export default function PhysicsPreviewScreen({ navigation, route }: Props) {
  const { garmentId } = route.params;
  return (
    <View style={styles.wrap}>
      <Text style={styles.title}>Physics preview</Text>
      <Text style={styles.body}>
        Garment #{garmentId}: integrate Marvelous / Blender cloth cache playback or Unity
        Cloth component. Expose timeline scrubber; run simulation off the UI thread.
      </Text>
      <Pressable style={styles.btn} onPress={() => navigation.goBack()}>
        <Text style={styles.btnText}>Back</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: colors.bg, padding: 24, paddingTop: 56 },
  title: { fontSize: 24, fontWeight: "800", color: colors.text, marginBottom: 12 },
  body: { color: colors.muted, lineHeight: 24, marginBottom: 24 },
  btn: {
    alignSelf: "flex-start",
    backgroundColor: colors.accent,
    paddingHorizontal: 20,
    paddingVertical: 14,
    borderRadius: 12,
  },
  btnText: { color: colors.bg, fontWeight: "700" },
});
