<?php

namespace App\Domain\D4\Import;

/**
 * Joins datamined objects to their localised text.
 *
 * There is no id inside a StringList tying it to its object: the join is a
 * filename convention. `base/meta/<Group>/<Name>.<ext>` is described by
 * `enUS_Text/meta/StringList/<Group>_<Name>.stl.json`. Label casing differs
 * per group (`Power` uses `name`/`desc`, `Affix`/`Item` use `Name`/`Desc`), so
 * every lookup here is case-insensitive.
 *
 * Many objects have no StringList at all (Aspect, PlayerClass, SkillKit, most
 * Tempered_* affixes, generic ParagonNodes); those resolve to an empty list
 * and callers fall back on their own.
 */
class StringResolver
{
    /**
     * StringList path => lowercased label => text.
     *
     * @var array<string, array<string, string>>
     */
    protected array $lists = [];

    /**
     * Lowercased `__eAttribute_name__` => display template.
     *
     * @var array<string, string>|null
     */
    protected ?array $attributeDescriptions = null;

    public function __construct(
        protected D4DataSource $source,
    ) {}

    /**
     * Every label of the StringList describing a base meta file, e.g.
     * "base/meta/Power/Barbarian_Whirlwind.pow". Keys are lowercased.
     *
     * @return array<string, string>
     */
    public function labels(string $metaFile): array
    {
        [$group, $name] = self::splitMetaFile($metaFile);

        return $this->labelsFor($group, $name);
    }

    /**
     * Case-insensitive single-label lookup for a base meta file path.
     */
    public function label(string $metaFile, string $label): ?string
    {
        return $this->labels($metaFile)[mb_strtolower($label)] ?? null;
    }

    /**
     * Every label of the StringList for an SNO group and object name. Keys are
     * lowercased.
     *
     * @return array<string, string>
     */
    public function labelsFor(string $group, string $name): array
    {
        $path = $this->pathFor($group, $name);

        return $this->lists[$path] ??= $this->load($path);
    }

    /**
     * Case-insensitive single-label lookup for an SNO group and object name.
     */
    public function labelFor(string $group, string $name, string $label): ?string
    {
        return $this->labelsFor($group, $name)[mb_strtolower($label)] ?? null;
    }

    /**
     * The generic display template for an attribute, used to generate text for
     * the affixes that ship without a StringList of their own. Variants scoped
     * to a power (`AoE_Size_Bonus_Per_Power#Barbarian_Rupture`) win over the
     * bare attribute name.
     */
    public function attributeDescription(string $attribute, ?string $scope = null): ?string
    {
        $this->attributeDescriptions ??= $this->load('json/enUS_Text/meta/StringList/AttributeDescriptions.stl.json');

        if ($scope !== null && $scope !== '') {
            $scoped = $this->attributeDescriptions[mb_strtolower($attribute.'#'.$scope)] ?? null;

            if ($scoped !== null) {
                return $scoped;
            }
        }

        return $this->attributeDescriptions[mb_strtolower($attribute)] ?? null;
    }

    /**
     * The StringList path for an SNO group and object name.
     */
    public function pathFor(string $group, string $name): string
    {
        return "json/enUS_Text/meta/StringList/{$group}_{$name}.stl.json";
    }

    /**
     * Split "base/meta/Power/Barbarian_Whirlwind.pow" into its group and name.
     *
     * @return array{0: string, 1: string}
     */
    public static function splitMetaFile(string $metaFile): array
    {
        $segments = explode('/', str_replace('\\', '/', trim($metaFile)));
        $file = (string) array_pop($segments);
        $group = (string) array_pop($segments);

        return [$group, self::stripExtension($file)];
    }

    /**
     * @return array<string, string>
     */
    protected function load(string $path): array
    {
        $list = $this->source->optionalJson($path);

        if ($list === null) {
            return [];
        }

        $labels = [];

        foreach ($list['arStrings'] ?? [] as $entry) {
            $label = $entry['szLabel'] ?? null;
            $text = $entry['szText'] ?? null;

            if (is_string($label) && $label !== '' && is_string($text)) {
                $labels[mb_strtolower($label)] = $text;
            }
        }

        return $labels;
    }

    protected static function stripExtension(string $file): string
    {
        if (str_ends_with($file, '.json')) {
            $file = mb_substr($file, 0, -5);
        }

        $dot = mb_strrpos($file, '.');

        return $dot === false ? $file : mb_substr($file, 0, $dot);
    }
}
