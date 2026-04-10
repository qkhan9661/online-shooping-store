import { NativeStackNavigationProp } from "@react-navigation/native-stack";
import React, { useCallback, useState } from "react";
import {
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useAuth } from "../auth";
import { apiFetch } from "../api";
import { colors } from "../theme";
import { RootStackParamList } from "../types";

type Garment = {
  id: number;
  sku: string;
  name: string;
  description: string | null;
  price: string;
};

type Props = {
  navigation: NativeStackNavigationProp<RootStackParamList, "ProductList">;
};

export default function ProductListScreen({ navigation }: Props) {
  const { token, logout } = useAuth();
  const [items, setItems] = useState<Garment[]>([]);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    if (!token) {
      return;
    }
    const res = await apiFetch("/garments", token);
    const data = await res.json();
    setItems(data.data ?? []);
  }, [token]);

  const onRefresh = async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  };

  React.useEffect(() => {
    load();
  }, [load]);

  return (
    <View style={styles.wrap}>
      <View style={styles.header}>
        <Text style={styles.title}>Catalog</Text>
        <View style={styles.headerActions}>
          <Pressable onPress={() => navigation.navigate("ScanInstructions")}>
            <Text style={styles.link}>New scan</Text>
          </Pressable>
          <Pressable onPress={logout}>
            <Text style={styles.linkOut}>Log out</Text>
          </Pressable>
        </View>
      </View>
      <FlatList
        data={items}
        keyExtractor={(item) => String(item.id)}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.accent} />}
        contentContainerStyle={{ paddingBottom: 32, gap: 12 }}
        renderItem={({ item }) => (
          <Pressable
            style={styles.card}
            onPress={() => navigation.navigate("ProductDetail", { garmentId: item.id })}
          >
            <Text style={styles.name}>{item.name}</Text>
            <Text style={styles.sku}>{item.sku}</Text>
            {item.description ? <Text style={styles.desc} numberOfLines={2}>{item.description}</Text> : null}
            <Text style={styles.price}>${item.price}</Text>
          </Pressable>
        )}
        ListEmptyComponent={<Text style={styles.empty}>Pull to refresh or add garments via API.</Text>}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, backgroundColor: colors.bg, paddingTop: 48, paddingHorizontal: 20 },
  header: { flexDirection: "row", justifyContent: "space-between", alignItems: "center", marginBottom: 16 },
  headerActions: { flexDirection: "row", gap: 16, alignItems: "center" },
  title: { fontSize: 28, fontWeight: "800", color: colors.text },
  link: { color: colors.accent, fontWeight: "600" },
  linkOut: { color: colors.muted, fontWeight: "600" },
  card: {
    backgroundColor: colors.card,
    borderRadius: 16,
    padding: 18,
    borderWidth: 1,
    borderColor: colors.border,
  },
  name: { color: colors.text, fontSize: 18, fontWeight: "700" },
  sku: { color: colors.muted, fontSize: 12, marginTop: 4 },
  desc: { color: colors.muted, marginTop: 8, lineHeight: 20 },
  price: { color: colors.accent, fontWeight: "800", marginTop: 10, fontSize: 16 },
  empty: { color: colors.muted, textAlign: "center", marginTop: 40 },
});
